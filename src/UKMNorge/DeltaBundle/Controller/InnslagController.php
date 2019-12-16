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
use UKMNorge\Arrangement\Load;
use UKMNorge\Geografi\Kommune;
use UKMNorge\Innslag\Innslag;
use UKMNorge\Innslag\Personer\Person;
use UKMNorge\Innslag\Personer\Venner;
use UKMNorge\Innslag\Typer\Typer;

require_once('UKM/Autoloader.php');

class InnslagController extends Controller
{
    /**
     * Lar brukeren velge arrangement
     * _@route: <ukmid/pamelding/>
     */
    public function geoAction()
    {
        $filter = new Filter();
        $filter->harPamelding();

        $season = $this->container->get('ukm_delta.season')->getActive();

        $view_data['translationDomain'] = 'innslag';

        $view_data['fylker'] = Fylker::getAll();
        $view_data['user'] = $this->get('ukm_user')->getCurrentUser();

        // Last inn alle arrangementer (med påmelding) per kommune
        foreach ($view_data['fylker'] as $fylke) {
            foreach ($fylke->getKommuner()->getAll() as $kommune) {
                $arrangementer = Load::forKommune(
                    $season,
                    $kommune,
                    $filter
                );

                $kommune->setAttr(
                    'arrangementer',
                    $arrangementer
                );

                if( $arrangementer->getAntall() == 0 ) {
                    $action = 'visIngenArrangement';
                    $link = '';
                } elseif( $arrangementer->getAntall() > 1 ) {
                    $action = 'visArrangementer';
                    $link = '';
                } elseif( $arrangementer->getAntall() == 1 && $arrangementer->getFirst()->erFellesmonstring() ) {
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
        $arrangement = $this->hentArrangement($pl_id);

        if (!$arrangement->erPameldingApen()) {
            throw new Exception('Påmeldingsfristen er ute!');
        }

        if( $arrangement->getInnslagTyper()->getAntall() == 1 ) {

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
     * Oppretter innslaget, og legger til kontaktperson hvis dette skal gjøres
     * _@route: <ukmid/pamelding/$k_id-$pl_id/$type/opprett/>
     * 
     * Videresender til rediger innslag etter oppretting
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     */
    public function createAction(Int $k_id, Int $pl_id, String $type )
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
                $innslag = $innslagService->hentEnkeltPersonInnslag($type, $arrangement, $person);
            } catch (Exception $e) {
                // Hvis personen ikke er påmeldt fra før, opprett en ved å fortsette.
                // Ignorerer derfor Exception $e
            }
        }

        // Opprett nytt innslag hvis vi ikke nettopp fant det
        if(!$innslag) {
            $innslag = $innslagService->opprett(
                $kommune,
                $arrangement,
                $type,
                $person
            );
        }

        // Lagre endringer på personobjektet
        if( $lagrePerson ) {
            $personService->lagre($person, $innslag->getId());
        }

        // Flytt personvern-tilbakemelding (nå lagret på delta user-objektet) over på person-objektet
        $personService->oppdaterPersonvern($innslag);

        $route_data['b_id'] = $innslag->getId();

        // Enkeltpersoner kan potensielt være ferdig påmeldt nå.
        // Ved å trigge lagre, trigges også evalueringen av mangler.
        if( $type->erEnkeltPerson() ) {
            $innslagService->lagre($innslag);
        }

        return $this->redirectToRoute(
            'ukm_delta_ukmid_pamelding_innslag_oversikt',
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
            'arrangement' => $this->get('ukm_api.arrangement')->hent($pl_id)
        ];

        // Enkeltperson-påmeldinger har enklere skjema
        if ($type->erEnkeltperson()) {
            // Hvis det er et enkeltperson-innslag, som hverken har
            // beskrivelse eller funksjoner, så har vi alt da. Tut og kjør, du er påmeldt!
            if( !$type->harBeskrivelse() && !$type->harFunksjoner() ) {
                return $this->redirectToRoute(
                    'ukm_delta_ukmid_pamelding_'. ($innslag->erPameldt() ? 'pameldt' : 'status'),
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

        // Hent inn innslaget
        $innslag = $innslagService->hent($b_id);

        if( $innslag->getType()->harBeskrivelse() ) {
            $innslag->setBeskrivelse($request->request->get('beskrivelse'));
        }

        // Hvis innslaget ikke har titler
        if ($innslag->getType()->erEnkeltperson()) {
            $person = $innslag->getPersoner()->getSingle();

            $innslag->setNavn($person->getNavn());

            if( $innslag->getType()->harFunksjoner() ) {
                $funksjoner = [];
                #$mulige = $innslag->getType()->getFunksjoner();
                foreach($request->request->get('funksjoner') as $element) {
                    $funksjoner[$element] = $innslag->getType()->getTekst( $element );// = $mulige[$element];
                }
                $person->setRolle( $funksjoner );
            }
            $personService->lagre($person, $innslag->getId());
            $innslagService->lagre( $innslag );
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_status', $view_data);
        }

        // Innslaget har titler
        $innslag->setNavn($request->request->get('navn'));
        if( $innslag->getType()->harSjanger() ) {
            $innslag->setSjanger($request->request->get('sjanger'));
        }

        $innslagService->lagre($innslag);

        // Hvis path er satt og ikke tom, så skal vi til et nytt sted (rediger person, for eksempel)
        if (!empty($request->request->get('path'))) {
            return $this->redirect($request->request->get('path'));
        }

        // Tilbake til statusAction
        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_status', $view_data);
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
        return $this->render(
            'UKMDeltaBundle:Innslag:person.html.twig',
            [
                'k_id' => $k_id,
                'pl_id' => $pl_id,
                'type' => Typer::getByKey($type),
                'type_key' => $type,
                'b_id' => $b_id,
                'translationDomain' => $type,
                'friends' => $this->_getVenner(
                    $this->get('ukm_api.innslag')->hent($b_id)
                )
            ]
        );
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
            $this->addFlash("danger", "Klarte ikke å lagre ".$request->request->get('fornavn'));
            $view_data['k_id'] = $k_id;
            $view_data['pl_id'] = $pl_id;
            $view_data['type'] = $type;
            $view_data['b_id'] = $b_id;

            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_ny_person', $view_data);
        }

        // Legg til i innslaget, sett rolle
        $person->setRolle($request->request->get('instrument'));

        // Sett alder
        $person->setFodselsdato(new DateTime(((int) date('Y') - $request->request->get('alder')) . '-01-01'));

        try {
            $innslagService->leggTilPerson( $innslag, $person );            
            $this->addFlash("success", "La til ".$person->getNavn());
        } catch (Exception $e) {
            $this->addFlash("danger", "Klarte ikke å legge til ".$person->getNavn()." i innslaget!");
            $this->get('logger')->error("Klarte ikke å legge til ".$person->getNavn() ." i innslag ".$innslag->getNavn().". Feil: ".$e->getMessage());
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
            'user' => $this->hentCurrentUser(),
            'person' => $this->get('ukm_api.person')->hent($p_id, $b_id),
            'innslag' => $this->get('ukm_api.innslag')->hent($b_id),
            'translationDomain' => $type
        ];
        return $this->render('UKMDeltaBundle:Innslag:person.html.twig', $view_data);
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

        // Lagre
        try {
            $this->get('ukm_api.person')->lagre($person, $innslag->getId());
            $this->addFlash("success", "Lagret endringer");
        } catch (Exception $e) {
            $this->addFlash("danger", "Klarte ikke å lagre endringer på ".$person->getNavn()."!");
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
        } catch ( Exception $e ) {
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
            'translationDomain' => $type,
            'innslag' => $innslagService->hent($b_id)
        ];

        return $this->_renderTitleAction($view_data);
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
            'innslag' => $innslag,
            'tittel' => $innslag->getTitler()->get($t_id)
        ];

        return $this->_renderTitleAction($view_data);
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
        if( $innslag->getType()->harTid() ) {
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

        try {
            $innslagService->lagreTitler($innslag, $tittel);
            $this->addFlash("success", "Lagret tittel-endringer!");
        } catch ( Exception $e ) {
            $this->addFlash("danger", "Klarte ikke å lagre tittel!");
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
        $tittel = $innslag->getTitler()->get($t_id);

        // Fjern tittelen
        try {
            $innslagService->fjernTittel($innslag, $tittel);    
            $this->addFlash("success", "Fjernet tittel!");
        } catch ( Exception $e ) {
            $this->get('logger')->error("Klarte ikke å fjerne tittel ".$t_id." fra innslag ".$b_id.". Feilmelding: ".$e->getCode()." - ".$e->getMessage());
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
        $innslag = $innslagService->hent($b_id);

        // Hvis POST-request, utfør
        if ($this->getRequest()->isMethod('POST')) {
            $innslagService->meldAv($innslag->getId(), $pl_id);
            $this->addFlash('success', $this->get('translator')->trans('removeAction.fjernet', ["%name" => $innslag->getNavn()], 'base'));
            return $this->redirectToRoute('ukm_delta_ukmid_homepage');
        }

        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id,
            'translationDomain' => 'base',
            'innslag' => $innslag
        ];
        return $this->render('UKMDeltaBundle:Innslag:fjern.html.twig', $view_data);
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
    public function attendingAction( Int $k_id, Int $pl_id, String $type, Int $b_id)
    {
        $arrangement = $this->get('ukm_api.arrangement')->hent( $pl_id );

        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id,
            'translationDomain' => 'innslag',
            'fb_share_caption' => $this->get('translator')->trans('fb_share', ['%monstring' => $arrangement->getNavn()], 'base'),
            'arrangement' => $arrangement,
            'innslag' => $this->get('ukm_api.innslag')->hent( $b_id )
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
    public function fristAction( Int $k_id, Int $pl_id, String $type, Int $b_id)
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
