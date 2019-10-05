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
use UKMNorge\Innslag\Typer;

require_once('UKM/Autoloader.php');

class InnslagController extends Controller
{
    /**
     * Handler for route <ukmid/pamelding/>
     * Lar brukeren velge arrangement
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
            }
        }

        $view_data['filter'] = $filter;

        return $this->render('UKMDeltaBundle:Innslag:geo.html.twig', $view_data);
    }

    /**
     * Handler for route <ukmid/pamelding/$k_id-$pl_id/>
     * Velg type innslag du ønsker å melde på.
     * Både mønstring og kommune er allerede valgt 
     * 
     * @param Int KommuneID
     * @param Int ArrangementID
     */
    public function typeAction(Int $k_id, Int $pl_id)
    {
        $arrangement = $this->hentArrangement($pl_id);

        if (!$arrangement->erPameldingApen()) {
            throw new Excecption('Påmeldingsfristen er ute!');
        }

        $view_data = [
            'arrangement' => $arrangement,
            'kommune' => $this->hentKommune($k_id),
            'user' => $this->hentCurrentUser()
        ];
        return $this->render('UKMDeltaBundle:Innslag:type.html.twig', $view_data);
    }

    /**
     * Handler for route <ukmid/pamelding/$k_id-$pl_id/$type>
     * Lar brukeren velge hvem som meldes på (meg alene, meg med flere, jeg er kun kontaktperson)
     *
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param [type] $translationDomain
     * @return void
     */
    public function whoAction(Int $k_id, Int $pl_id, String $type, $translationDomain)
    {
        $view_data = [
            'translationDomain' => $translationDomain,
            'arrangement' => $this->hentArrangement($pl_id),
            'kommune' => $this->hentKommune($k_id),
            'user' => $this->hentCurrentUser(),
            'type' => Typer::getByName($type)
        ];
        return $this->render('UKMDeltaBundle:Innslag:who.html.twig', $view_data);
    }


    /**
     * Handler for route <ukmid/pamelding/$k_id-$pl_id/$type/opprett/$hvem/>
     * Oppretter innslaget, og legger til kontaktperson hvis dette skal gjøres
     * 
     * Videresender til rediger innslag etter oppretting
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param String $hvem
     */
    public function createAction(Int $k_id, $pl_id, $type, $hvem)
    {
        $route_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'hvem' => $hvem
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

        // Hvis brukeren ikke er registrert i systemet fra før
        if ($user->getPameldUser() === null) {
            // Opprett person
            $person = $personService->opprett($user->getFirstname(), $user->getLastname(), $user->getPhone(), $arrangement->getId());
            // Sett alder og e-post basert på user-bundle-alder
            $person->setAlder($user->getBirthdate());
            $person->setEpost($user->getEmail());
            $personService->save($person);
            // Oppdater verdier i UserBundle
            $user->setPameldUser($person->getId());
            $this->container->get('fos_user.user_manager')->updateUser($user);
        }
        // Hvis brukeren er registrert i systemet fra før
        else {
            $person = $personService->hent($user->getPameldUser());
        }

        // Hvis brukeren (kontaktpersonen) allerede er påmeldt på denne mønstringen
        // i denne _tittelløse_ kategorien, gå til redigering
        if (!$type->harTitler()) {
            try {
                $innslag = $innslagService->hentPameldingFraTittellos($type, $arrangement, $person);
                $route_data['b_id'] = $innslag->getId();
                return $this->redirectToRoute(
                    'ukm_delta_ukmid_pamelding_innslag_oversikt',
                    $route_data
                );
            } catch (Exception $e) {
                // Hvis personen ikke er påmeldt fra før, opprett en ved å fortsette.
                // Ignorerer derfor Exception $e
            }
        }

        // Opprett et nytt innslag
        $innslag = $innslagService->opprett(
            new Kommune($k_id),
            $arrangement,
            $type,
            $hvem,
            $person
        );

        if ($hvem == 'alene') {
            $innslag->setNavn($person->getNavn());
            $innslagService->lagre($innslag);
        }

        // Flytt personvern-tilbakemelding (nå lagret på delta user-objektet) over på person-objektet
        $personService->oppdaterPersonvern($innslag);

        $route_data['b_id'] = $innslag->getId();

        return $this->redirectToRoute(
            'ukm_delta_ukmid_pamelding_innslag_oversikt',
            $route_data
        );
    }

    /**
     * Handler for route <ukmid/pamelding/$k_id-$pl_id/$type/$b_id/?hvem=$hvem>
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
        $innslagService->sjekkBandtype($innslag, $type); // Vil printe RedirectResponse og kaste Exception

        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type->getKey(),
            'b_id' => $b_id,
            'translationDomain' => $type->getKey(),
            'user' => $user,
            'innslag' => $innslag,
            'kommune' => $this->get('ukm_api.geografi')->hentKommune($k_id),
            'arrangement' => $this->get('ukm_api.arrangement')->hent($pl_id)
        ];

        // Innslag uten titler har enklere skjema
        if (!$type->harTitler()) {
            return $this->render('UKMDeltaBundle:Innslag:oversikt_tittellos.html.twig', $view_data);
        }

        // Hvis hvem-variabelen blir sendt med.
        $request = Request::createFromGlobals();
        if (!empty($request->get('hvem'))) {
            $view_data['hvem'] = $request->get('hvem');
        }

        return $this->render('UKMDeltaBundle:Innslag:oversikt.html.twig', $view_data);
    }


    /**
     * Handler for POST (lagring for) route <ukmid/pamelding/$k_id-$pl_id/$type/$b_id/?hvem=$hvem>
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

        // Hent inn innslaget
        $innslag = $innslagService->hent($b_id);

        // Hvis innslaget ikke har titler
        if ($innslag->getType()->erJobbeMed()) {
            throw new Exception('TODO: Funksjonen er ikke implementert');

            $innslagService->lagreBeskrivelse($b_id, $desc);
            $personService = $this->get('ukm_api.person');

            switch ($type) {
                case 'nettredaksjon':
                case 'arrangor':
                    $tittellos_person = $this->_hent_tittellos_person($b_id);
                    $instrument_object = $request->request->get('funksjoner');
                    $funksjon = '';
                    if (is_array($instrument_object)) {
                        foreach ($instrument_object as $current_instrument) {
                            $funksjon .= $this->get('translator')->trans('funksjon.' . $current_instrument, array(), $type) . ', ';
                        }
                        $funksjon = rtrim($funksjon, ', ');
                    }

                    $innslagService->lagreInstrumentTittellos($b_id, $tittellos_person->g('p_id'), $pl_id, $funksjon, $instrument_object);
                    break;
            }
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_status', $view_data);
        }

        // Innslaget har titler

        $innslag->setBeskrivelse($request->request->get('beskrivelse'));
        $innslag->setNavn($request->request->get('navn'));
        if (in_array($type, ['musikk', 'litteratur', 'film', 'video', 'annet', 'dans', 'teater'])) {
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
     * Handler for route <ukmid/pamelding/$k_id-$pl_id/$type/$b_id/ny-person>
     * Viser skjema for å legge til person
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
                'type' => $type,
                'b_id' => $b_id,
                'translationDomain' => $type,
                'friends' => $this->_getVenner(
                    $this->get('ukm_api.innslag')->hent($b_id)
                )
            ]
        );
    }

    /**
     * Hent alle venner som ikke er med i innslaget
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
     * Handler for POST (lagre for) route <ukmid/pamelding/$k_id-$pl_id/$type/$b_id/ny-person>
     * Oppretter ny person og legger til i innslaget
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

        // Opprett personen
        $person = $personService->opprett(
            $request->request->get('fornavn'),
            $request->request->get('etternavn'),
            $request->request->get('mobil'),
            $kommune,
            $arrangement
        );

        // Legg til i innslaget, sett rolle
        $person->setRolle($request->request->get('instrument'));
        $innslag->getPersoner()->leggTil($person);


        // Sett alder
        $person->setFodselsdato(new DateTime(((int) date('Y') - $request->request->get('alder')) . '-01-01'));


        Write::savePersoner($innslag);
        PersonWrite::save($person);

        $view_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
            'b_id' => $b_id
        ];

        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    /**
     * Handler for route </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/status/>
     * 
     * Viser enten "du er påmeldt" eller "dette mangler før du er påmeldt"
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
     * Handler for route </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/p$p_id/>
     * Redigering av person
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
            'type' => $type,
            'b_id' => $b_id,
            'user' => $this->hentCurrentUser(),
            'person' => $this->get('ukm_api.person')->hent($p_id, $b_id),
            'innslag' => $this->get('ukm_api.innslag')->hent($b_id),
            'translationDomain' => $type
        ];
        return $this->render('UKMDeltaBundle:Innslag:person.html.twig', $view_data);
    }

    /**
     * Handler for POST (lagre for) route </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/p$p_id/lagre/>
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
        $person->setInstrument($request->request->get('instrument'));

        // Lagre
        $this->get('ukm_api.person')->lagre($person, $innslag->getId());

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

        $this->get('ukm_api.innslag')->fjernPerson($b_id, $p_id);

        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $route_data);
    }

    /**
     * Handler for route </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/teknisk/>
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
     * Handler for POST (lagre for) route </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/teknisk/lagre/>
     * Lagrer tekniske behov
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

        $innslag = $innslagService->hent($b_id);
        $innslag->setTekniskeBehov($request->request->get('teknisk'));
        $innslagService->lagre($innslag);

        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $route_data);
    }

    /**
     * Handler for route </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/tittel/>
     * Legg til en ny tittel
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
            'type' => $type,
            'b_id' => $b_id,
            'translationDomain' => $type,
            'innslag' => $innslagService->hent($b_id)
        ];

        return $this->_renderTitleAction($view_data);
    }

    /**
     * Handler for route </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/tittel/$t_id>
     * Rediger eksisterende tittel
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
            'type' => $type,
            'b_id' => $b_id,
            'translationDomain' => $type,
            'innslag' => $innslag,
            'tittel' => $innslag->getTitler()->get($t_id)
        ];

        return $this->_renderTitleAction($view_data);
    }

    /**
     * Hjelper for newTitleAction og editTitleAction
     * Disse gjør det samme, edit slenger bare på tittel som 
     * data i view.
     *
     * @param Array $view_data
     * @return 
     */
    private function _renderTitleAction(array $view_data)
    {
        switch ($view_data['type']) {
            case 'musikk':
                return $this->render('UKMDeltaBundle:Musikk:tittel.html.twig', $view_data);
            case 'dans':
                return $this->render('UKMDeltaBundle:Dans:tittel.html.twig', $view_data);
            case 'teater':
                return $this->render('UKMDeltaBundle:Teater:tittel.html.twig', $view_data);
            case 'film':
                return $this->render('UKMDeltaBundle:Film:tittel.html.twig', $view_data);
            case 'litteratur':
                return $this->render('UKMDeltaBundle:Litteratur:tittel.html.twig', $view_data);
            case 'utstilling':
                return $this->render('UKMDeltaBundle:Utstilling:tittel.html.twig', $view_data);
            case 'matkultur':
                return $this->render('UKMDeltaBundle:Matkultur:tittel.html.twig', $view_data);
            default:
                return $this->render('UKMDeltaBundle:Annet:tittel.html.twig', $view_data);
        }
    }

    /**
     * Handler for POST (lagre for) route </ukmid/pamelding/$k_id-$pl_id/$type/$b_id/tittel/lagre/>
     * Legg til en ny tittel
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
        $tittel->setSesong($seasonService->getActive());

        switch ($innslag->getType()->getKey()) {
                // Musikk
            case 'musikk':
                $tittel->setVarighet($request->request->get('lengde'));
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
                $tittel->setVarighet($request->request->get('lengde'));
                $tittel->setInstrumental($request->request->get('sangtype') == 'instrumental' ? 1 : 0);
                $tittel->setSelvlaget($request->request->get('selvlaget') == '1');
                $tittel->setMelodiAv($request->request->get('melodiforfatter'));
                $tittel->setTekstAv($request->request->get('tekstforfatter'));
                break;
                // Dans
            case 'dans':
                $tittel->setVarighet($request->request->get('lengde'));
                $tittel->setKoreografi($request->request->get('koreografi'));
                break;
                // Litteratur
            case 'litteratur':
                $tittel->setTekstAv($request->request->get('tekstforfatter'));
                if ($request->request->get('leseopp') == '1') {
                    $tittel->setLitteraturLesOpp(true);
                    $tittel->setVarighet($request->request->get('lengde'));
                } else {
                    $tittel->setLitteraturLesOpp(false);
                    $tittel->setVarighet(0);
                }
                break;
                // Utstilling
            case 'utstilling':
                $tittel->setType($request->request->get('type'));
                break;
                // Film
            case 'film':
            case 'video':
                $tittel->setVarighet($request->request->get('lengde'));
                break;
                // Annet
            case 'annet':
            case 'scene':
                $tittel->setErfaring($request->request->get('erfaring'));
                $tittel->setKommentar($request->request->get('kommentar'));
                break;
            default:
                throw new Exception(
                    'Beklager, prøvde å lagre en ukjent tittel-type'
                );
        }

        $innslagService->lagreTitler($innslag, $tittel);
        // Lagre tittel
        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

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
        $tittel = $innslag->getTitler()->get( $t_id );

        // Fjern tittelen
        $innslagService->fjernTittel( $innslag, $tittel );
        
        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }























    public function pameldingAction()
    {
        throw new Exception('TODO: Funksjonen er ikke implementert');
        $view_data = array();

        $view_data['user'] = $this->get('ukm_user')->getCurrentUser();
        return $this->render('UKMDeltaBundle:Innslag:pamelding.html.twig', $view_data);
    }

    public function create_tittellosAction($k_id, $pl_id, $type)
    {
        throw new Exception('TODO: Funksjonen er ikke implementert');

        return $this->createAction($k_id, $pl_id, $type, 'alene');
    }

    public function removeAction($k_id, $pl_id, $type, $b_id)
    {
        throw new Exception('TODO: Funksjonen er ikke implementert');

        // Output confirm-vindu
        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['translationDomain'] = 'base';
        // Sjekk tilgang
        $innslagService = $this->get('ukm_api.innslag');

        $innslagService->sjekk($b_id, $type);
        $innslag = $innslagService->hent($b_id);

        // If post-request, i.e. JA-knapp.
        if ($this->getRequest()->isMethod('POST')) {
            $innslagService->meldAv($b_id, $pl_id);
            $this->addFlash('success', $this->get('translator')->trans('removeAction.fjernet', array("%name" => $innslag->get('b_name')), 'base'));
            return $this->redirectToRoute('ukm_delta_ukmid_homepage');
        }
        // Else render

        $view_data['innslag'] = $innslag;
        $view_data['navn'] = $innslag->get('b_name');
        return $this->render('UKMDeltaBundle:Innslag:fjern.html.twig', $view_data);
    }



    public function attendingAction($k_id, $pl_id, $type, $b_id)
    {
        throw new Exception('TODO: Funksjonen er ikke implementert');

        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['translationDomain'] = 'innslag';

        require_once('UKM/monstring.class.php');
        $monstring = new monstring($pl_id);

        $start = new DateTime();
        $start->setTimestamp($monstring->get('old_pl_start'));

        $name = $monstring->get('pl_name');


        // Tekst som deles på facebook!
        $view_data['fb_share_caption'] = $this->get('translator')->trans('fb_share', array('%monstring' => $name), 'base');

        $view_data['pl_navn'] = $name;
        $view_data['pl_start'] = $start;
        //$view_data['pl_link'] = $monstring->get('pl_link');

        $monstring_v2 = new monstring_v2($pl_id);
        $view_data['pl_link'] = $monstring_v2->getLink();

        return $this->render('UKMDeltaBundle:Innslag:pameldt.html.twig', $view_data);
    }

    public function fristAction($k_id, $pl_id, $type, $b_id)
    {
        throw new Exception('TODO: Funksjonen er ikke implementert');

        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['translationDomain'] = 'base';

        $innslagService = $this->get('ukm_api.innslag');
        $view_data['innslag'] = $innslagService->hent($b_id);
        return $this->render('UKMDeltaBundle:Innslag:frist.html.twig', $view_data);
    }

    private function _hent_tittellos_person($b_id)
    {
        throw new Exception('TODO: Funksjonen er ikke implementert');

        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');
        $innslag = $innslagService->hent($b_id);
        $personer = $innslag->personer();
        $p_id = $personer[0]['p_id'];
        return $personService->hent($p_id, $b_id);
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
