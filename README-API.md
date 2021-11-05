# Introduksjon

API for å få tilgang til å opprette, lagre, slette eller modifisere ting på arrangørsystemet. Dette API-et skal brukes fra nytt design.


## Test1


## Authorization

For å få tilgang til resurser gjennom API kall, må man være logged inn, derfor bruk av verktøy som Postman er ikke mulig.


## Innslag
```http
GET /api/new_innslag/
```

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `k_id` | `integer [0-9]{1,4}` | **Required**. kommune id |
| `k_id` | `integer [0-9]{1,4}` | **Required**. kommune id |
| `type` | `string [a-z]+` | **Required**. type kan være: 'Musikk' 'Dans' 'Teater' 'Litteratur' 'Annet' 'Utstilling' 'Film' 'Cosplay' 'Dataspill' 'Matkultur' |

## Svar eksempel

Dette er et svar eksempel når et nytt innslag opprettes.

```javascript
{
   "context":{
      "type":"monstring",
      "sesong":null,
      "monstring":{
         "id":3829,
         "type":"kommune",
         "sesong":2021,
         "kommuner":[
            5441
         ],
         "fylke":54
      },
      "innslag":{
         "id":93944,
         "type":"utstilling"
      },
      "forestilling":null,
      "videresend_til":false,
      "kontaktperson":null,
      "delta_user_id":null
   },
   "id":93944,
   "navn":"Innslag uten navn",
   "type":{
      "id":3,
      "key":"utstilling",
      "name":"Utstilling",
      "tekst":{
         "rolle.navn":"Skriv inn hvilken rolle %person har, eller hva %person gjør",
         "rolle.placeholder":"F.eks maler, tegner, illustratør, skulptør osv.",
         "rolle.hjelp":"",
         "rolle.ukjent":"Ukjent rolle",
         "titler.pronomen":"et",
         "titler.pronomen_adjektiv":"nytt",
         "titler.entall":"verk/utstillingsobjekt",
         "titler.flertall":"verk/utstillingsobjekt",
         "titler.bestemt":"kunstverket/utstillingsobjektet",
         "titler.placeholder":"F.eks. Elg i Solnedgang, Vakker bru osv.",
         "sjanger.navn":"Type og teknikk",
         "sjanger.placeholder":"F.eks. portrettfoto, akvarellmaling, acryl osv.",
         "artistnavn.alene.navn":"Navn / kunstnernavn / navn på gruppe",
         "artistnavn.alene.placeholder":"F.eks. Kunst-%fornavn, Kunstgjengen fra %etternavn, %fornavn %etternavn...",
         "artistnavn.sammen.navn":"Kunstnernavn / navn på gruppe",
         "artistnavn.sammen.placeholder":"F.eks. Kunstlinja, painting cowboys, %etternavn-gjengen..."
      },
      "type":"gruppe",
      "frist":1,
      "er_scene":false,
      "har_titler":true,
      "har_sjanger":false,
      "har_funksjoner":true,
      "har_tekniske_behov":false,
      "har_nominasjon":false,
      "har_filmer":false,
      "har_bilder":true,
      "funksjoner":null,
      "tabell":"smartukm_titles_exhibition",
      "autfollow_personer":false,
      "kategori":"vise",
      "har_tid":false,
      "har_beskrivelse":true
   },
   "beskrivelse":"",
   "kommune_id":"5441",
   "kommune":{
      
   },
   "fylke":null,
   "filmer":false,
   "program":null,
   "kategori":"",
   "sjanger":"",
   "playback":null,
   "personer_collection":{
      "context":{
         "type":"monstring",
         "sesong":null,
         "monstring":{
            "id":3829,
            "type":"kommune",
            "sesong":2021,
            "kommuner":[
               5441
            ],
            "fylke":54
         },
         "innslag":{
            "id":93944,
            "type":"utstilling"
         },
         "forestilling":null,
         "videresend_til":false,
         "kontaktperson":null,
         "delta_user_id":null
      },
      "personer":null,
      "personer_videresendt":null,
      "personer_ikke_videresendt":null,
      "debug":false,
      "simple_count":null,
      "id":null
   },
   "artikler_collection":null,
   "bilder_collection":null,
   "attributes":{
      "order":null
   },
   "sesong":"2021",
   "avmeldbar":false,
   "advarsler":null,
   "mangler":null,
   "mangler_json":"",
   "titler":null,
   "home":null,
   "home_id":3829,
   "delta_eier":"",
   "er_videresendt":null,
   "nominasjoner":null,
   "kontaktperson_id":"107528",
   "kontaktperson":null,
   "tekniske_behov":"",
   "videresendt_til":null,
   "log":null,
   "subscriptionTime":"1636110548",
   "status":"0"
}
```

`context` inneholder generelle informasjon om arrangement.


## Status Codes

Delta-API returneres disse API status koder:

| Status Code | Description |
| :--- | :--- |
| 200 | `OK` |
| 201 | `CREATED` |
| 400 | `BAD REQUEST` |
| 403 | `FORBIDDEN` |
| 404 | `NOT FOUND` |
| 500 | `INTERNAL SERVER ERROR` |

