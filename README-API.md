# Introduksjon

API for å få tilgang til å opprette, lagre, slette eller modifisere ting på arrangørsystemet fra DELTA. Dette API-et skal brukes på nytt design.

## Authorization

For å få tilgang til resurser gjennom API kall, må man være logged inn, derfor bruk av verktøy som Postman er ikke mulig.


## Innslag
```https
POST /api/new_innslag/
```

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `k_id` | `integer [0-9]{1,4}` | **Required**: kommune id |
| `pl_id` | `integer [0-9]{1,5}` | **Required**: arrangement id |
| `type` | `string [a-z]+` | **Required**: type kan være: 'Musikk', 'Dans', 'Teater', 'Litteratur', 'Annet', 'Utstilling', 'Film', 'Cosplay', 'Dataspill', 'Matkultur'. |

### Svar eksempel

Dette er ett svar eksempel når ett nytt innslag opprettes.

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


<br />

```https
POST /api/remove_innslag/
```

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `pl_id` | `integer [0-9]{1,5}` | **Required**: arrangement id |
| `b_id` | `integer [0-9]{1,11}` | **Required**: innslag id |

### Svar eksempel

Dette er ett svar eksempel når ett innslag fjernes.

```javascript
{success: "Innslaget \"asg\" ble meldt av."}
```


<br />

```https
GET /api/get_innslag_types/{pl_id}
```

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `pl_id` | `integer [0-9]{1,5}` | **Required**: arrangement id |

### Svar eksempel


```javascript
[
   {
      "id":1,
      "key":"dans",
      "name":"Dans",
      "tekst":{
         "rolle.navn":"Rolle",
         "rolle.placeholder":"F.eks danser, koreograf osv...",
         "rolle.hjelp":"Skriv inn hva %person gjør i dansenummeret",
         "rolle.ukjent":"Ukjent rolle.",
         "titler.pronomen":"en",
         "titler.pronomen_adjektiv":"ny",
         "titler.entall":"koreografi / dans",
         "titler.flertall":"koreografier / danser",
         "titler.bestemt":"koreografien / dansen",
         "titler.placeholder":"Eks. Svanesjøen, Love me like you do, Spretten mix osv...",
         "sjanger.navn":"sjanger",
         "sjanger.placeholder":"Eks samba, ballett, moderne...",
         "artistnavn.alene.navn":"Artistnavn / dansegruppe / crew",
         "artistnavn.alene.placeholder":"Eks. Danse-%fornavn, %fornavn %etternavn, %etternavn dance crew",
         "artistnavn.sammen.navn":"Dansegruppe / artistnavn / crew",
         "artistnavn.sammen.placeholder":"Eks. Superdanserne, We Dance, Folkedanserne..."
      },
      "type":"gruppe",
      "frist":1,
      "er_scene":true,
      "har_titler":true,
      "har_sjanger":true,
      "har_funksjoner":true,
      "har_tekniske_behov":true,
      "har_nominasjon":false,
      "har_filmer":true,
      "har_bilder":true,
      "funksjoner":null,
      "tabell":"smartukm_titles_scene",
      "autfollow_personer":true,
      "kategori":"vise",
      "har_tid":true,
      "har_beskrivelse":true
   },
   ...
]
```

<br>
<br>


## Fylke og kommuner

```https
GET /api/get_all_fylker_og_kommuner/
```
### Svar eksempel
```javascript
{
   "3":{
      "id":3,
      "link":"oslo",
      "navn":"Oslo",
      "attributes":null,
      "kommuner":[
         {
            "id":316,
            "navn":"Alna",
            "erAktiv":true,
            "action":false,
            "link":false
         },
         ...
      ],
      "nettverk_omrade":null,
      "fake":false,
      "active":true
   },
   ...
}
```


## Fylke

```https
GET /api/get_all_fylker/
```
### Svar eksempel
```javascript
{
   "Agder":{
      "id":42,
      "link":"agder",
      "navn":"Agder",
      "attributes":null,
      "kommuner":null,
      "nettverk_omrade":null,
      "fake":false,
      "active":true
   },
   "Innlandet":{
      "id":34,
      "link":"innlandet",
      "navn":"Innlandet",
      "attributes":null,
      "kommuner":null,
      "nettverk_omrade":null,
      "fake":false,
      "active":true
   },
   ...
}
```

<br>
<br>


```https
GET /api/get_fylke/{fylke_id}
```

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `fylke_id` | `integer [0-9]{1,5}` | **Required**: fylke id |

### Svar eksempel
```javascript
{
   "Agder":{
      "id":42,
      "link":"agder",
      "navn":"Agder",
      "attributes":null,
      "kommuner":null,
      "nettverk_omrade":null,
      "fake":false,
      "active":true
   }
}
```

<br>
<br>

## Kommune

```https
GET /api/get_kommuner_i_fylke/{fylke_id}
```

Hent alle kommuner i et fylke

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `fylke_id` | `integer [0-9]{1,5}` | **Required**: fylke id |

```javascript
[
   {
      "id":4203,
      "navn":"Arendal",
      "erAktiv":true,
      "action":false,
      "link":false
   },
   ...
]
```

<br>
<br>

## Innslag
```https
POST /api/new_person/
```

Legg til en person i innslag

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `k_id` | `integer ([0-9]{1,4}`) | **Required**: kommune id |
| `pl_id` | `integer [0-9]{1,5}` | **Required**: arrangement id |
| `type` | `string [a-z]+` | **Required**: type kan være: 'Musikk', 'Dans', 'Teater', 'Litteratur', 'Annet', 'Utstilling', 'Film', 'Cosplay', 'Dataspill', 'Matkultur'. |
| `b_id` | `integer ([0-9]{1,11}`) | **Required**: Innslag id |
| `fornavn` | `string ([^0-9]+`) | **Required**: person fornavn |
| `etternavn` | `string ([^0-9]+`) | **Required**: person etternavn |
| `alder` | `integer ([0-9]{1,2}`) | **Required**: person alder |
| `mobil` | `integer ([0-9]{8}`) | **Required**: person mobilnummer |
| `rolle` | `string ([^0-9]+`) | **Required**: person rolle |

### Svar eksempel

Dette er ett svar eksempel når en ny person legges til i innslaget.

```javascript
{
   "fornavn" : "Ola",
   "etternavn" : "Normann",
   "alder" : 15,
   "mobil" : 12345678,
   "rolle" : "Tegner"
}
```

<br />
<br />
<br />

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

