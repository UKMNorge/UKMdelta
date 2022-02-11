<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

use UKMNorge\Geografi\Fylker;

use Exception;
use DateTime;
use stdClass;
use UKMNorge\Arrangement\Arrangement;
use UKMNorge\Arrangement\Filter;
use UKMNorge\Arrangement\Kommende;
use UKMNorge\Arrangement\Skjema\Skjema;
use UKMNorge\Arrangement\Skjema\SvarSett;
use UKMNorge\Arrangement\Skjema\Write;
use UKMNorge\Geografi\Kommune;
use UKMNorge\Innslag\Innslag;
use UKMNorge\Innslag\Personer\Person;
use UKMNorge\Innslag\Personer\Venner;
use UKMNorge\Innslag\Typer\Typer;

require_once('UKM/Autoloader.php');

class InnslagController extends Controller
{

    /**
     * Velg riktig lenke i kommune-listen
     *
     * Når bruker velger kommune er det ulike actions basert på
     * hvor mange arrangement kommunen har, og hvorvidt det er
     * fellesmønstring eller ikke.
     * 
     * For påmelding til fylkesarrangement, peker lenken til 
     * påmelding for fylkes-arrangementet, men siden vi må vite hvilken
     * kommune deltakeren kommer fra, bruker vi samme listen som mellomtrinn.
     * 
     * @param Kommune $kommune
     * @param Filter $filter
     * @return void
     */
    private function setKommuneLinkActionAttr(Kommune $kommune, Filter $filter)
    {
        $arrangementer = Kommende::forKommune(
            $kommune,
            $filter
        );

        $kommune->setAttr(
            'arrangementer',
            $arrangementer
        );

        if ($arrangementer->getAntall() == 0) {
            $action = 'visIngenArrangement';
            $link = '';
        } elseif ($arrangementer->getAntall() > 1) {
            $action = 'visArrangementer';
            $link = '';
        } elseif ($arrangementer->getAntall() == 1 && $arrangementer->getFirst()->erFellesmonstring()) {
            $action = 'visKommuner';
            $link = '';
        } else {
            $action = 'visDirektelenke';
            $link = $this->generateUrl(
                'ukm_delta_ukmid_pamelding_hva',
                [
                    'k_id' => $kommune->getId(),
                    'pl_id' => $arrangementer->getFirst()->getId()
                ]
            );
        }
        $kommune->setAttr('action', $action);
        $kommune->setAttr('link', $link);
    }

    /**
     * Lar brukeren velge arrangement
     * _@route: <ukmid/pamelding/>
     */
    public function geoAction()
    {
        $request = Request::createFromGlobals();

        $filter = new Filter();
        $filter->harPamelding();

        $view_data['translationDomain'] = 'innslag';

        $view_data['fylker'] = Fylker::getAll();
        if (date('Y') == '2020') {
            $view_data['fylker'][] = Fylker::getById(33);
        }
        $view_data['user'] = $this->get('ukm_user')->getCurrentUser();
        $view_data['pameldUserId'] = $this->hentCurrentUser()->getPameldUser();


        // Last inn alle arrangementer (med påmelding) per kommune
        foreach ($view_data['fylker'] as $fylke) {
            foreach ($fylke->getKommuner()->getAll() as $kommune) {
                $this->setKommuneLinkActionAttr($kommune, $filter);
            }
        }

        // Prøv å laste inn den forhåndsvalgte kommunen.
        if ($request->cookies->has("lastlocation")) {
            try {
                $kommune = new Kommune($request->cookies->get("lastlocation"));
                $this->setKommuneLinkActionAttr($kommune, $filter);
                $view_data['suggested_kommune'] = $kommune;
                $view_data['suggested_fylke'] = $kommune->getFylke();
            } catch (Exception $e) {
                // Ikke advar om feil, foreslått kommune er "bonus-feature".
            }
        }

        $view_data['filter'] = $filter;

        return $this->render('UKMDeltaBundle:Innslag:geo.html.twig', $view_data);
    }

    /**
     * Velg type innslag du ønsker å melde på. Både mønstring og kommune er allerede valgt 
     * _@route: <ukmid/pamelding/$k_id-$pl_id/>
     * 
     * @param Int KommuneID
     * @param Int ArrangementID
     */
    public function typeAction(Int $k_id, Int $pl_id)
    {   
        // Hvis kommunen ikke er aktiv, redirect til kommunesiden
        $kommune = $this->hentKommune($k_id);
        if(!$kommune->erAktiv()) {
            header('Location: https:' . $kommune->getOvertattAv()->getLink());
            exit;
        }

        $arrangement = $this->hentArrangement($pl_id);

        if (!$arrangement->erPameldingApen()) {
            throw new Exception('Påmeldingsfristen er ute!');
        }

        // Verify user data here as well - in case people are coming from direct links
        if( $this->get('ukm_user')->getCurrentUser()->getBirthdate() == null ||
            $this->get('ukm_user')->getCurrentuser()->getSamtykke() === null
        ) {
            $this->get('session')->set('checkInfoRedirect', 'ukm_delta_ukmid_pamelding_hva');
            $this->get('session')->set('checkInfo_kid', $k_id);
            $this->get('session')->set('checkInfo_plid', $pl_id);
            return $this->redirectToRoute( 'ukm_delta_ukmid_checkinfo');
        }
        $this->get('session')->remove('checkInfoRedirect');
        $this->get('session')->remove('checkInfo_kid');
        $this->get('session')->remove('checkInfo_plid');

        if ($arrangement->getInnslagTyper()->getAntall() == 1) {

            $tillatt_type = $arrangement->getInnslagTyper()->getAll()[0];

            return $this->redirectToRoute(
                'ukm_delta_ukmid_pamelding_v2_opprett',
                [
                    'k_id' => $k_id,
                    'pl_id' => $pl_id,
                    'type' => $tillatt_type->getKey()
                ]
            );
        }

        $view_data = [
            'arrangement' => $arrangement,
            'kommune' => $this->hentKommune($k_id),
            'user' => $this->hentCurrentUser()
        ];
        return $this->render('UKMDeltaBundle:Innslag:type.html.twig', $view_data);
    }

    /**
     * Før vi kan opprette innslag på fylke-nivå må vi la brukeren velge kommune
     * _@route: <ukmid/pamelding/fylke-$pl_id/>
     * 
     * Videresender til rediger innslag etter oppretting
     * @param Int $pl_id
     */
    public function fylkePreTypeAction(Int $pl_id)
    {
        $filter = new Filter();
        $filter->harPamelding();

        $view_data = [
            'translationDomain' => 'innslag',
            'show' => 'whereyoufrom'
        ];

        $arrangement = new Arrangement($pl_id);
        if ($arrangement->getMetaValue('nedslagsfelt') == 'fylke') {
            $view_data['fylker'] = [$arrangement->getFylke()];
        } else {
            $view_data['fylker'] = Fylker::getAll();
            if (date('Y') == '2020') {
                $view_data['fylker'][] = Fylker::getById(33);
            }
        }

        $view_data['user'] = $this->get('ukm_user')->getCurrentUser();

        // Foreslå kommune basert på siste påmelding deltakeren hadde
        $mine_innslag = $this->get('ukm_api.innslag')->hentInnslagFraKontaktperson();

        if ($mine_innslag->getAntall() > 0) {
            $innslagene = [];
            foreach ($mine_innslag->getAll() as $innslag) {
                /**
                 * @var Innslag $innslag 
                 */
                $innslagene[$innslag->getId()] = $innslag->getKommune();
            }
            $kommune = array_pop($innslagene);
            // Hvis arrangementet tillater påmelding kun i fylket, kan vi kun
            // foreslå kommuner i det fylket
            if (
                $arrangement->getMetaValue('nedslagsfelt') == 'land' ||
                ($arrangement->getMetaValue('nedslagsfelt') == 'fylke' &&
                    $kommune->getFylke()->getId() == $arrangement->getFylke()->getId())
            ) {
                $this->setKommuneLinkActionAttrFylke($kommune, $pl_id);
                $view_data['suggested_kommune'] = $kommune;
                $view_data['suggested_fylke'] = $kommune->getFylke();
            }
        }

        // Last inn alle arrangementer (med påmelding) per kommune
        foreach ($view_data['fylker'] as $fylke) {
            foreach ($fylke->getKommuner()->getAll() as $kommune) {
                $this->setKommuneLinkActionAttrFylke($kommune, $pl_id);
            }
        }

        return $this->render('UKMDeltaBundle:Innslag:geo.html.twig', $view_data);
    }

    /**
     * Lag riktig type-lenke for påmelding til fylke-arrangement
     * 
     * Brukes av geoAction-listen, når deltakeren melder seg på til et
     * fylkesarrangement.
     */
    private function setKommuneLinkActionAttrFylke(Kommune $kommune, Int $arrangement_id)
    {
        $kommune->setAttr(
            'link',
            $this->generateUrl(
                'ukm_delta_ukmid_pamelding_hva',
                [
                    'k_id' => $kommune->getId(),
                    'pl_id' => $arrangement_id,
                ]
            )
        );
        $kommune->setAttr('action', 'visDirektelenke');
    }

    /**
     * Oppretter innslaget, og legger til kontaktperson hvis dette skal gjøres
     * _@route: <ukmid/pamelding/$k_id-$pl_id/$type/opprett/>
     * 
     * Videresender til rediger innslag etter oppretting
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     */
    public function createAction(Int $k_id, Int $pl_id, String $type)
    {
        
        $route_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
        ];

        // Setup input data
        $type = Typer::getByKey($type);
        $user = $this->hentCurrentUser();
        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');

        // Hent arrangement og sjekk at det er mulig å melde på innslag
        $arrangement = new Arrangement($pl_id);
        if (!$arrangement->erPameldingApen($type->getFrist())) {
            throw new Exception('Påmeldingsfristen er ute!');
        }

        // Hvis arrangement
        if(!$innslagService->ledigPlassPaaArrangement($arrangement)) {
            $this->addFlash('danger', "Oops! Desverre er det ikke ledig plass lenger!");
            return $this->redirectToRoute(
                'ukm_delta_homepage',
                $route_data
            );
        }

        $kommune = new Kommune($k_id);

        // Hvis brukeren ikke er registrert i systemet fra før
        if ($user->getPameldUser() === null) {
            // Opprett person
            $person = $personService->opprett(
                $user->getFirstname(),
                $user->getLastname(),
                $user->getPhone(),
                $kommune,
                $arrangement
            );
            // Sett alder og e-post basert på user-bundle-alder
            $person->setFodselsdato($user->getBirthdate());
            $person->setEpost($user->getEmail());

            // Oppdater verdier i UserBundle
            $user->setPameldUser($person->getId());
            $this->container->get('fos_user.user_manager')->updateUser($user);

            // Se om brukeren har fått tildelt en Wordpress-innloggingsbruker (via UKMusers etc), og prøv å koble den.
            $personService = $this->container->get('ukm_api.person');
            $personService->addDeltaIDToWordpressLoginUser($person->getId(), $user->getId());

            $lagrePerson = true;
        }
        // Hvis brukeren er registrert i systemet fra før
        else {
            $person = $personService->hent($user->getPameldUser());
            $lagrePerson = false;
        }

        $innslag = false;
        // Hvis brukeren (kontaktpersonen) allerede er påmeldt på denne mønstringen
        // i denne _tittelløse_ kategorien, gå til redigering
        if ($type->erEnkeltPerson()) {
            try {
                $innslag = $innslagService->hentEnkeltPersonInnslag($type, $arrangement);
            } catch (Exception $e) {
                // Hvis personen ikke er påmeldt fra før, opprett en ved å fortsette.
                // Ignorerer derfor Exception $e
            }
        }

        // Opprett nytt innslag hvis vi ikke nettopp fant det
        if (!$innslag) {
            $innslag = $innslagService->opprett(
                $kommune,
                $arrangement,
                $type,
                $person
            );
        }

        // Lagre endringer på personobjektet
        if ($lagrePerson) {
            $personService->lagre($person, $innslag->getId());
        }

        // Flytt personvern-tilbakemelding (nå lagret på delta user-objektet) over på person-objektet
        $personService->oppdaterPersonvern($innslag);

        $route_data['b_id'] = $innslag->getId();

        // Enkeltpersoner kan potensielt være ferdig påmeldt nå.
        // Ved å trigge lagre, trigges også evalueringen av mangler.
        if ($type->erEnkeltPerson()) {
            try{
                $innslagService->lagre($innslag);
            }catch(Exception $e) {
                if($e->getCode() == 584000) {
                    $this->addFlash('danger', "Oops! Desverre er det ikke ledig plass lenger!");
                }
                else {
                    throw $e;
                }
            }
        }

        return $this->redirectToRoute(
            'ukm_delta_ukmid_pamelding_innslag_oversikt',
            $route_data
        );
    }

    /**
     * Legg til bruker i venteliste
     * _@route: </ukmid/pamelding/{k_id}-{pl_id}/venteliste/>
     * 
     * Videresender til rediger innslag etter oppretting
     * @param Int $k_id
     * @param Int $pl_id
     */
    public function ventelisteAction(Int $k_id, Int $pl_id)
    {
        $personService = $this->get('ukm_api.person');

        $route_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
        ];

        $arrangement = new Arrangement($pl_id);
        $venteliste = $arrangement->getVenteliste();

        // Hvis det er ledig plass på arrangementet så stopp prosessen.
        // Eksempel: Brukeren trykker knappen 'set meg i venteliste', mens en annen bruker er meld av og da blir ledig plass og trenger ikke brukeren å være i venteliste
        if($arrangement->getAntallPersoner() < $arrangement->getMaksAntallDeltagere()) {
            $this->addFlash('danger', "Oops! noe gikk feil! Prøv igjen");
            return $this->redirectToRoute(
                'ukm_delta_ukmid_pamelding',
                $route_data
            );    
        }

        $kommune = new Kommune($k_id);

        $user = $this->hentCurrentUser();

        // Hvis brukeren ikke er registrert i systemet fra før
        if ($user->getPameldUser() === null) {
            // Opprett person
            $person = $personService->opprett(
                $user->getFirstname(),
                $user->getLastname(),
                $user->getPhone(),
                $kommune,
                $arrangement
            );
            // Sett alder og e-post basert på user-bundle-alder
            $person->setFodselsdato($user->getBirthdate());
            $person->setEpost($user->getEmail());

            // Oppdater verdier i UserBundle
            $user->setPameldUser($person->getId());
            $this->container->get('fos_user.user_manager')->updateUser($user);

            // Se om brukeren har fått tildelt en Wordpress-innloggingsbruker (via UKMusers etc), og prøv å koble den.
            $personService = $this->container->get('ukm_api.person');
            $personService->addDeltaIDToWordpressLoginUser($person->getId(), $user->getId());
        }
        // Hvis brukeren er registrert i systemet fra før
        else {
            $person = $personService->hent($user->getPameldUser());
        }

        try {
            $venteliste->addPerson($person, $kommune);
        } catch(Exception $e) {
            $this->get('logger')->error("UKMDeltaBundle:Innslag:venteliste - Feil oppsto i forbindelse med lagring av person i venteliste! Feilkode: " . $e->getCode() . ". Melding: " . $e->getMessage());
            $this->addFlash('danger', "Oops! Klarte ikke å lagre endringene. Feilkode: " . $e->getCode());
        }

        return $this->redirectToRoute(
            'ukm_delta_homepage',
            $route_data
        );

    }

    public function create_tittellosAction($k_id, $pl_id, $type)
    {
        return $this->createAction($k_id, $pl_id, $type, 'alene');
    }

    /**
     * Vis informasjon om et innslag (oversiktssiden)
     * _@route: <ukmid/pamelding/$k_id-$pl_id/$type/$b_id/>
     *
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @return void
     */
    public function overviewAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $innslagService = $this->get('ukm_api.innslag');

        $user = $this->hentCurrentUser();
        $type = Typer::getByKey($type);
        $innslag = $innslagService->hent($b_id);

        // Sjekk tilgang og rett bandtype
        $innslagService->sjekk($innslag);
        #$innslagService->sjekkBandtype($innslag, $type); // Vil printe RedirectResponse og kaste Exception

        /** @var Arrangement $arrangement */
        $arrangement = $this->get('ukm_api.arrangement')->hent($pl_id);

        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'type_key' => strtolower($type->getKey() == 'video' ? 'film' : $type->getKey()),
            'b_id' => $b_id,
            'translationDomain' => $type->getKey() == 'video' ? 'film' : $type->getKey(),
            'user' => $user,
            'innslag' => $innslag,
            'kommune' => $this->get('ukm_api.geografi')->hentKommune($k_id),
            'arrangement' => $arrangement
        ];

        // Enkeltperson-påmeldinger har enklere skjema
        if ($type->erEnkeltperson()) {
            // Hvis det er et enkeltperson-innslag, som hverken har
            // beskrivelse eller funksjoner, så har vi alt da. Tut og kjør, du er påmeldt!
            if (!$type->harBeskrivelse() && !$type->harFunksjoner()) {

                // Hvis arrangementet trenger et ekstra-skjema
                if ($arrangement->harDeltakerSkjema()) {
                    return $this->redirectToRoute(
                        'ukm_delta_ukmid_pamelding_extras',
                        [
                            'k_id' => $k_id,
                            'pl_id' => $pl_id,
                            'type' => $type->getKey(),
                            'b_id' => $b_id
                        ]
                    );
                }

                return $this->redirectToRoute(
                    'ukm_delta_ukmid_pamelding_' . ($innslag->erPameldt() ? 'pameldt' : 'status'),
                    [
                        'k_id' => $k_id,
                        'pl_id' => $pl_id,
                        'type' => $type->getKey(),
                        'b_id' => $b_id
                    ]
                );
            }
            return $this->render('UKMDeltaBundle:Innslag:oversikt_enkeltperson.html.twig', $view_data);
        }

        return $this->render('UKMDeltaBundle:Innslag:oversikt.html.twig', $view_data);
    }

    /**
     * Lagre skjema med ekstra spørsmål fra arrangøren
     * _@route: POST (lagre) <ukmid/pamelding/$k_id-$pl_id/$type/$b_id/extras/>
     * 
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     */
    public function extraSaveAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $innslagService = $this->get('ukm_api.innslag');
        /** @var Innslag $innslag */
        $innslag = $innslagService->hent($b_id);
        /** @var Person $kontaktperson */
        $kontaktperson = $innslag->getKontaktperson();
        /** @var Arrangement $arrangement */
        $arrangement = $this->get('ukm_api.arrangement')->hent($pl_id);

        // Skjema som skal fylles ut
        $skjema = $arrangement->getDeltakerSkjema();
        $svarsett = $this->getSvarsett($skjema, $kontaktperson);

        foreach ($_POST as $key => $value) {
            if (strpos($key, 'sporsmal_') === 0) {
                @list($trash, $id, $field) = explode('_', $key);
                $svarsett->setSvar($id, $value);
            }
        }

        Write::saveSvarSett($svarsett);

        return $this->redirectToRoute(
            'ukm_delta_ukmid_pamelding_' . ($innslag->erPameldt() ? 'pameldt' : 'status'),
            [
                'k_id' => $k_id,
                'pl_id' => $pl_id,
                'type' => $type,
                'b_id' => $b_id
            ]
        );
    }

    /**
     * Vis skjema med ekstra spørsmål fra arrangøren
     * _@route: GET <ukmid/pamelding/$k_id-$pl_id/$type/$b_id/extras/>
     * 
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     */
    public function extraAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $innslagService = $this->get('ukm_api.innslag');
        /** @var Innslag $innslag */
        $innslag = $innslagService->hent($b_id);
        /** @var Person $kontaktperson */
        $kontaktperson = $innslag->getKontaktperson();
        /** @var Arrangement $arrangement */
        $arrangement = $this->get('ukm_api.arrangement')->hent($pl_id);

        // Skulle ikke vært her, da arrangementet ikke bruker
        // skjema. Videresend til statussiden.
        if (!$arrangement->harDeltakerSkjema()) {
            return $this->redirectToRoute(
                'ukm_delta_ukmid_pamelding_' . ($innslag->erPameldt() ? 'pameldt' : 'status'),
                [
                    'k_id' => $k_id,
                    'pl_id' => $pl_id,
                    'type' => $type,
                    'b_id' => $b_id
                ]
            );
        }

        // Hent skjema
        $skjema = $arrangement->getDeltakerSkjema();
        $svarsett = $this->getSvarsett($skjema, $kontaktperson);


        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id,
            'arrangement' => $arrangement,
            'svarsett' => $svarsett,
            'skjema' => $skjema
        ];

        return $this->render('UKMDeltaBundle:Innslag:extras.html.twig', $view_data);
    }

    /**
     * Hent svarsett for gitt person
     * 
     * @param Skjema $skjema
     * @param Person $person
     * @return SvarSett
     */
    private function getSvarSett(Skjema $skjema, Person $person)
    {
        try {
            $respondent = $skjema->getRespondenter()->get($person->getId());
            $svarsett = $respondent->getSvar();
        } catch (Exception $e) {
            if ($e->getCode() == 163003) {
                $svarsett = SvarSett::getPlaceholder('person', $person->getId(), $skjema->getId());
            }
        }
        return $svarsett;
    }

    /**
     * Lagrer alle endringer i et innslag
     * _@route: POST (lagre) <ukmid/pamelding/$k_id-$pl_id/$type/$b_id/>
     * 
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @return void
     */
    public function saveOverviewAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id
        ];

        $request = Request::createFromGlobals();
        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');

        try {
            // Hent inn innslaget
            $innslag = $innslagService->hent($b_id);

            if ($innslag->getType()->harBeskrivelse()) {
                $innslag->setBeskrivelse($request->request->get('beskrivelse'));
            }

            // Hvis innslaget ikke har titler
            if ($innslag->getType()->erEnkeltperson()) {
                $person = $innslag->getPersoner()->getSingle();

                $innslag->setNavn($person->getNavn());

                if ($innslag->getType()->harFunksjoner() && $request->request->get('funksjoner') != null) {
                    $funksjoner = [];

                    foreach ($request->request->get('funksjoner') as $element) {
                        $funksjoner[$element] = $innslag->getType()->getTekst($element); // = $mulige[$element];
                    }
                    $person->setRolle($funksjoner);
                }
                $personService->lagre($person, $innslag->getId());
                $innslagService->lagre($innslag);
                return $this->redirectToRoute('ukm_delta_ukmid_pamelding_status', $view_data);
            }

            // Innslaget har titler
            $innslag->setNavn($request->request->get('navn'));
            if ($innslag->getType()->harSjanger()) {
                $innslag->setSjanger($request->request->get('sjanger'));
            }

            $innslagService->lagre($innslag);

            // Hvis path er satt og ikke tom, så skal vi til et nytt sted (rediger person, for eksempel)
            if (!empty($request->request->get('path'))) {
                return $this->redirect($request->request->get('path'));
            }

            // Tilbake til statusAction
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_status', $view_data);
        } catch (Exception $e) {
            $this->get('logger')->error("UKMDeltaBundle:Innslag:saveOverview - Feil oppsto i forbindelse med lagring av innslagsdata! Feilkode: " . $e->getCode() . ". Melding: " . $e->getMessage());
            $this->addFlash('danger', "Oops! Klarte ikke å lagre endringene. Feilkode: " . $e->getCode());
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
        }
    }

    /**
     * Viser statusider. Enten "du er påmeldt" eller "dette mangler før du er påmeldt"
     * _@route: </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/status/>
     * 
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     */
    public function statusAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $route_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id
        ];

        $innslagService = $this->get('ukm_api.innslag');
        $innslag = $innslagService->hent($b_id);

        if ($innslag->erPameldt()) {
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_pameldt', $route_data);
        }

        $view_data = [
            'translationDomain' => $type,
            'arrangement' => $this->get('ukm_api.arrangement')->hent($pl_id),
            'innslag' => $innslag
        ];

        return $this->render('UKMDeltaBundle:Innslag:status.html.twig', array_merge($route_data, $view_data));
    }

    /**
     * Viser skjema for å legge til person
     * _@route: <ukmid/pamelding/$k_id-$pl_id/$type/$b_id/ny-person>
     *
     * @param Int $k_id
     * @param Int $pl_id
     * @param String] $type
     * @param Int $b_id
     * @return void
     */
    public function newPersonAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'b_id' => $b_id,
        ];
        try {
            return $this->render(
                'UKMDeltaBundle:Innslag:person.html.twig',
                array_merge($view_data, [
                    'type' => Typer::getByKey($type),
                    'type_key' => $type,
                    'translationDomain' => $type,
                    'friends' => $this->_getVenner(
                        $this->get('ukm_api.innslag')->hent($b_id)
                    )
                ])
            );
        } catch (Exception $e) {
            $this->get('logger')->error("UKMDeltaBundle:Innslag:newPerson - Feil oppsto ved uthenting av data til newPerson! Feilkode: " . $e->getCode() . ". Melding: " . $e->getMessage());
            $this->addFlash('danger', "Oops! Klarte ikke å legge til en ny person. Feilkode: " . $e->getCode());

            return $this->redirectToRoute(
                'ukm_delta_ukmid_pamelding_innslag_oversikt',
                array_merge($view_data, [
                    'type' => $type
                ])
            );
        }
    }

    /**
     * Hent alle venner som ikke er med i innslaget.
     * Støttefunksjon for å legge til person i innslaget (ikke rediger person)
     *
     * @param Innslag $innslag
     * @return Array<Person>
     */
    private function _getVenner(Innslag $innslag)
    {
        $venner = Venner::exclude(
            $innslag->getPersoner()->getAllIds(),
            Venner::getAll(
                $this->hentCurrentUser()->getPameldUser(),
                $innslag->getId()
            )
        );

        return $venner;
    }

    /**
     * Oppretter ny person og legger til i innslaget
     * _@route POST (lagre) <ukmid/pamelding/$k_id-$pl_id/$type/$b_id/ny-person>
     * 
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @return void
     */
    public function saveNewPersonAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $request = Request::createFromGlobals();
        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');

        $view_data['k_id'] = $k_id;
        $view_data['pl_id'] = $pl_id;
        $view_data['type'] = $type;
        $view_data['b_id'] = $b_id;

        try {

            $innslag = $innslagService->hent($b_id);
            $arrangement = new Arrangement($pl_id);
            $kommune = new Kommune($k_id);

            $mobil = $request->request->get('mobil');

            try {
                // Opprett personen
                $person = $personService->opprett(
                    $request->request->get('fornavn'),
                    $request->request->get('etternavn'),
                    $mobil,
                    $kommune,
                    $arrangement
                );
            } catch (Exception $e) {
                // Får vi ikke til å opprette personen, går vi tilbake til skjemaet. Andre feil, gå til oversikten.
                $this->get('logger')->error(
                    "UKMDeltaBundle:Innslag:saveNewPerson - Klarte ikke å opprette person på innslag " . $b_id . ". Feilkode: " . $e->getCode() . ". Melding: " . $e->getMessage() . ".\n\nData: ",
                    [$request->request->get('fornavn'), $request->request->get('etternavn'), $mobil, $kommune, $arrangement]
                );
                $this->addFlash("danger", "Klarte ikke å lagre " . $request->request->get('fornavn'));

                return $this->redirectToRoute('ukm_delta_ukmid_pamelding_ny_person', $view_data);
            }

            // Legg til i innslaget, sett rolle
            $person->setRolle($request->request->get('instrument'));

            // Sett alderf
            $person->setFodselsdato(new DateTime(((int) date('Y') - $request->request->get('alder')) . '-01-01'));

            $innslagService->leggTilPerson($innslag, $person);
            $this->addFlash("success", "La til " . $person->getNavn());
        } catch (Exception $e) {
            $this->get('logger')->error("Klarte ikke å legge til " . $person->getNavn() . " i innslag " . $innslag->getNavn() . ". Feil: " . $e->getMessage());
            $this->addFlash("danger", "Klarte ikke å legge til " . $person->getNavn() . " i innslaget! Feilkode: " . $e->getCode());
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
        }

        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id
        ];

        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    /**
     * Vis skjema for redigering av person
     * _@route: </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/p$p_id/>
     * 
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @param Int $p_id
     */
    public function editPersonAction(Int $k_id, Int $pl_id, String $type, Int $b_id, Int $p_id)
    {
        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => Typer::getByKey($type),
            'type_key' => $type,
            'b_id' => $b_id,
            'translationDomain' => $type
        ];

        try {
            $view_data['user'] = $this->hentCurrentUser();
            $view_data['person'] = $this->get('ukm_api.person')->hent($p_id, $b_id);
            $view_data['innslag'] = $this->get('ukm_api.innslag')->hent($b_id);
            return $this->render('UKMDeltaBundle:Innslag:person.html.twig', $view_data);
        } catch (Exception $e) {

            // Oppsto det en feil mens vi prøvde å sende brukerne til rediger person-siden, sett en flashbag og send de tilbake til oversikten.
            $view_data['type'] = $type;
            $this->addFlash('danger', "Klarte ikke å redigere person. Systemet sa: " . $e->getMessage());
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
        }
    }

    /**
     * Lagre en redigert person.
     * _@route: POST (lagre) </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/p$p_id/lagre/>
     * 
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @param Int $p_id
     * @return void
     */
    public function savePersonAction(Int $k_id, Int $pl_id, String $type, Int $b_id, Int $p_id)
    {
        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id
        ];

        // Setup service og request
        $request = Request::createFromGlobals();
        $innslagService = $this->get('ukm_api.innslag');

        // Peis alle feil og advarsler (unntatt feil med request etc) i en flashbag + logging
        try {

            // Hent innslag
            $innslag = $innslagService->hent($b_id);
            $person = $innslag->getPersoner()->get($p_id);

            // Oppdater objektet
            $person->setFornavn($request->request->get('fornavn'));
            $person->setEtternavn($request->request->get('etternavn'));
            $person->setMobil($request->request->get('mobil'));
            $person->setFodselsdato(
                Person::getFodselsdatoFromAlder(
                    $request->request->get('alder')
                )
            );
            $person->setRolle($request->request->get('instrument'));

            $this->get('ukm_api.person')->lagre($person, $innslag->getId());
            $this->addFlash("success", "Lagret endringer");
        } catch (Exception $e) {
            $this->get('logger')->error("UKMDeltaBundle:Innslag:savePerson - Klarte ikke å lagre endringer på " . $request->request->get('fornavn') . ". Systemet sa: " . $e->getCode() . ", " . $e->getMessage());
            $this->addFlash("danger", "Klarte ikke å lagre endringer på " . $request->request->get('fornavn') . "! Feilkode: " . $e->getCode());
        }

        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    /**
     * Handler for POST (lagre for) route </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/p$p_id/fjern/>
     * Fjerner en person fra innslaget
     *
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @param Int $p_id
     * @return void
     */
    public function removePersonAction(Int $k_id, Int $pl_id, String $type, Int $b_id, Int $p_id)
    {
        $this->get('logger')->notice("DeltaBundle:removePerson - Remove person request received for person " . $p_id . " from band " . $b_id . ".");
        $route_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id,
            'p_id' => $p_id
        ];

        try {
            $this->get('ukm_api.innslag')->fjernPerson($b_id, $p_id);
            $this->addFlash("success", "Lagret endringer");
        } catch (Exception $e) {
            $this->addFlash("danger", "Klarte ikke å lagre endringer");
        }

        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $route_data);
    }

    /**
     * _@route: </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/teknisk/>
     * Rediger tekniske behov
     *
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @return void
     */
    public function technicalAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id,
            'translationDomain' => $type,
            'innslag' => $this->get('ukm_api.innslag')->hent($b_id)
        ];
        return $this->render('UKMDeltaBundle:Innslag:teknisk.html.twig', $view_data);
    }

    /**
     * Lagrer tekniske behov
     * _@route POST (lagre) </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/teknisk/lagre/>
     *
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @return void
     */
    public function saveTechnicalAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $route_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id,
        ];

        $request = Request::createFromGlobals();
        $innslagService = $this->get('ukm_api.innslag');

        try {
            $innslag = $innslagService->hent($b_id);
            $innslag->setTekniskeBehov($request->request->get('teknisk'));
            $innslagService->lagre($innslag);
            $this->addFlash("success", "Lagret tekniske behov");
        } catch (Exception $e) {
            $this->get('logger')->errror("UKMDeltaBundle:Innslag:saveTechnical - Klarte ikke å lagre tekniske behov. Feilkode: " . $e->getCode() . ". Melding: " . $e->getMessage(), $route_data);
            $this->addFlash("danger", "Klarte ikke å lagre tekniske behov");
        }

        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $route_data);
    }

    /**
     * Legg til en ny tittel
     * _@route: </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/tittel/>
     *
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @return void
     */
    public function newTitleAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $innslagService = $this->get('ukm_api.innslag');

        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => Typer::getByKey($type),
            'type_key' => $type,
            'b_id' => $b_id,
            'translationDomain' => $type
        ];

        try {

            $view_data['innslag'] = $innslagService->hent($b_id);
            return $this->_renderTitleAction($view_data);
        } catch (Exception $e) {
            $this->get('logger')->error("UKMDeltaBundle:Innslag:newTitle - Klarte ikke å vise ny tittel-siden. Feilkode: " . $e->getCode() . ". Melding: " . $e->getMessage(), $view_data);
            $this->addFlash('danger', "Klarte ikke å legge til en ny tittel! Feilkode: " . $e->getCode());
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
        }
    }

    /**
     * Rediger eksisterende tittel
     * _@route: </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/tittel/$t_id>
     *
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @return void
     */
    public function editTitleAction($k_id, $pl_id, $type, $b_id, $t_id)
    {
        $innslag = $this->get('ukm_api.innslag')->hent($b_id);

        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => Typer::getByKey($type),
            'type_key' => $type,
            'b_id' => $b_id,
            'translationDomain' => $type,
        ];
        try {
            $view_data['innslag'] = $innslag;
            $view_data['tittel'] = $innslag->getTitler()->get($t_id);
            return $this->_renderTitleAction($view_data);
        } catch (Exception $e) {
            $this->get('logger')->error("UKMDeltaBundle:Innslag:newTitle - Klarte ikke å laste inn tittelen. Feilkode: " . $e->getCode() . ". Melding: " . $e->getMessage(), $view_data);
            $this->addFlash('danger', "Klarte ikke å redigere tittel. Feilkode: " . $e->getCode());
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
        }
    }

    /**
     * Hjelper for newTitleAction og editTitleAction
     * Funksjonene gjør det samme, edit slenger bare på tittel som data i view.
     *
     * @param Array $view_data
     * @return 
     */
    private function _renderTitleAction(array $view_data)
    {
        $view_data['arrangement'] = $this->get('ukm_api.arrangement')->hent($view_data['pl_id']);
        
        switch ($view_data['type_key']) {
            case 'musikk':
                return $this->render('UKMDeltaBundle:Tittel:musikk.html.twig', $view_data);
            case 'dans':
                return $this->render('UKMDeltaBundle:Tittel:dans.html.twig', $view_data);
            case 'teater':
                return $this->render('UKMDeltaBundle:Tittel:teater.html.twig', $view_data);
            case 'film':
                return $this->render('UKMDeltaBundle:Tittel:film.html.twig', $view_data);
            case 'litteratur':
                return $this->render('UKMDeltaBundle:Tittel:litteratur.html.twig', $view_data);
            case 'utstilling':
                return $this->render('UKMDeltaBundle:Tittel:utstilling.html.twig', $view_data);
            case 'matkultur':
                return $this->render('UKMDeltaBundle:Tittel:matkultur.html.twig', $view_data);
            default:
                return $this->render('UKMDeltaBundle:Tittel:skjema.html.twig', $view_data);
        }
    }

    /**
     * Legg til en ny tittel
     * Handler for POST (lagre for) route </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/tittel/lagre/>
     *
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     */
    public function saveTitleAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id
        ];

        try {
            $request = Request::createFromGlobals();
            $seasonService = $this->get('ukm_delta.season');
            $innslagService = $this->get('ukm_api.innslag');

            $innslag = $innslagService->hent($b_id);

            // Opprett tittel
            if ($request->request->get('t_id') == 'new') {
                $tittel = $innslagService->opprettTittel($innslag);
            }
            // Hent tittel
            else {
                $tittel = $innslag->getTitler()->get($request->request->get('t_id'));
            }

            // Sett standard-info
            $tittel->setTittel($request->request->get('tittel'));
            if ($innslag->getType()->harTid()) {
                $tittel->setVarighet($request->request->get('lengde'));
            }

            switch ($innslag->getType()->getKey()) {
                    // Musikk
                case 'musikk':
                    $tittel->setSelvlaget($request->request->get('selvlaget') == '1');
                    $tittel->setMelodiAv($request->request->get('melodiforfatter'));

                    if ($request->request->get('sangtype') == 'instrumental') {
                        $tittel->setInstrumental(true);
                    } else {
                        $tittel->setInstrumental(false);
                        $tittel->setTekstAv($request->request->get('tekstforfatter'));
                    }
                    break;
                    // Teater
                case 'teater':
                    $tittel->setSelvlaget($request->request->get('selvlaget') == '1');
                    $tittel->setTekstAv($request->request->get('tekstforfatter'));
                    break;
                    // Dans
                case 'dans':
                    $tittel->setSelvlaget($request->request->get('selvlaget') == '1');
                    $tittel->setKoreografi($request->request->get('koreografi'));
                    break;
                    // Litteratur
                case 'litteratur':
                    $tittel->setTekstAv($request->request->get('tekstforfatter'));
                    if ($request->request->get('leseopp') == '1') {
                        $tittel->setLesOpp(true);
                    } else {
                        $tittel->setLesOpp(false);
                        $tittel->setVarighet(0);
                    }
                    break;
                    // Utstilling
                case 'utstilling':
                    $tittel->setType($request->request->get('type'));
                    break;
            }

            $innslagService->lagreTitler($innslag, $tittel);
            $this->addFlash("success", "Lagret tittel-endringer!");
        } catch (Exception $e) {
            $this->addFlash("danger", "Klarte ikke å lagre tittel! Feilkode: " . $e->getCode());
        }

        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    /**
     * Slett en tittel fra innslaget
     * _@route POST (lagre) </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/tittel/lagre/>
     *
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @param Int $t_id
     */
    public function deleteTitleAction(Int $k_id, Int $pl_id, String $type, Int $b_id, Int $t_id)
    {
        $innslagService = $this->get('ukm_api.innslag');

        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id,
            't_id' => $t_id
        ];

        // Hent tittel
        $innslag = $innslagService->hent($b_id);
        // Fix #309 - brukere har fått "Finner ikke tittel XX i innslaget"-feil. Mulig fordi den allerede er slettet i en tidligere request.
        // Vi feiler gracefully her, med å late som om det var en vellykka sletting
        // Dersom noen tror de er lurere enn oss og prøver å fjerne en tittel fra et annet innslag vil det stå at det funka, men ikke gjøre det 😈
        // Vi logger denne feilen litt hardere, for å se om vi finner andre feil enn "Klarte ikke å finne tittel xx i innslag."
        try {
            $tittel = $innslag->getTitler()->get($t_id);
        } catch (Exception $e) {
            $this->get('logger')->error("Innslag:deleteTitle - Klarte ikke å hente tittel for sletting. Dette kan være at tittelen allerede er slettet, eller en grovere systemfeil. Brukeren har fått en hyggelig beskjed om at sletting funket. Feilkode: " . $e->getCode() . ", melding: " . $e->getMessage() . ".");
            $this->addFlash('success', "Fjernet tittel");
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
        }

        // Fjern tittelen
        try {
            $innslagService->fjernTittel($innslag, $tittel);
            $this->addFlash("success", "Fjernet tittel!");
        } catch (Exception $e) {
            $this->get('logger')->error("Klarte ikke å fjerne tittel " . $t_id . " fra innslag " . $b_id . ". Feilmelding: " . $e->getCode() . " - " . $e->getMessage());
            $this->addFlash("danger", "Klarte ikke å fjerne tittel");
        }

        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    /**
     * "Slett" innslag. Markeres i db som status:77, og er i praksis borte fra systemet
     *
     * _@route </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/fjern/>
     * Viser bekreftelse
     *
     * _@route POST (utfør) </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/fjern/>
     * Utfører avmelding, og markerer innslaget som slettet
     * 
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @return void
     */
    public function removeAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $innslagService = $this->get('ukm_api.innslag');
        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id,
            'translationDomain' => 'base'
        ];

        try {
            $innslag = $innslagService->hent($b_id);

            // Hvis POST-request, utfør
            if ($this->getRequest()->isMethod('POST')) {
                $innslagService->meldAv($innslag->getId(), $pl_id);
                $this->addFlash('success', $this->get('translator')->trans('removeAction.fjernet', ["%name" => $innslag->getNavn()], 'base'));
                return $this->redirectToRoute('ukm_delta_ukmid_homepage');
            }

            $view_data['innslag'] = $innslag;
            

            return $this->render('UKMDeltaBundle:Innslag:fjern.html.twig', $view_data);
        } catch (Exception $e) {
            $this->get('logger')->error("Klarte ikke å melde av innslag " . $b_id . ". Feilmelding: " . $e->getCode() . " - " . $e->getMessage());
            $this->addFlash("danger", "Klarte ikke å melde av innslaget. Feilkode: " . $e->getCode());
            return $this->redirectToRoute('ukm_delta_ukmid_homepage');
        }
    }

    /**
     * Viser info om arrangementet deltakeren nettopp har blitt påmeldt!
     * _@route </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/pameldt/>
     *
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @return void
     */
    public function attendingAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $arrangement = $this->get('ukm_api.arrangement')->hent($pl_id);

        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id,
            'translationDomain' => 'innslag',
            'fb_share_caption' => $this->get('translator')->trans('fb_share', ['%monstring' => $arrangement->getNavn()], 'base'),
            'arrangement' => $arrangement,
            'innslag' => $this->get('ukm_api.innslag')->hent($b_id)
        ];

        return $this->render('UKMDeltaBundle:Innslag:pameldt.html.twig', $view_data);
    }

    /**
     * Vis informasjon om at påmeldingsfristen har gått ut
     * _@route: </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/frist/>
     *
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     * @return void
     */
    public function fristAction(Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id,
            'translationDomain' => 'base',
            'innslag' => $this->get('ukm_api.innslag')->hent($b_id)
        ];

        return $this->render('UKMDeltaBundle:Innslag:frist.html.twig', $view_data);
    }

    /**
     * Hent kommune med gitt ID
     *
     * @param Int $kommuneID
     * @return Kommune
     */
    private function hentKommune(Int $kommuneID)
    {
        return $this->container->get('ukm_api.geografi')->hentKommune($kommuneID);
    }

    /**
     * Hent arrangement med gitt ID
     *
     * @param Int $arrangementID
     * @return Arrangement
     */
    private function hentArrangement(Int $arrangementID)
    {
        return $this->container->get('ukm_api.arrangement')->hent($arrangementID);
    }

    /**
     * Hent aktiv bruker
     *
     * @return 
     */
    public function hentCurrentUser()
    {
        return $this->get('ukm_user')->getCurrentUser();
    }
}
