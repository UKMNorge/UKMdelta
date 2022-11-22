### OBS: Denne pakken er forlatt og vedlikeholdes ikke lenger. Erstates av https://github.com/UKMNorge/DeltaV5



Symfony-app for påmeldingssystemet
========================

# Slette cache
#### Hver gang ny kode skal lanseres må:
1. Bytt brukeren fra "DIN BRUKER" til ukmdelta ved å bruke `su ukmdelta` på WHM
2. `bin/console cache:clear --env=dev; bin/console cache:clear --env=prod` skal kjøres på /home/ukmdelta/public_html

# Setup
Ikke dokumentert

#### x. Sett opp cron-jobb
UKMDesignBundle henter sitemap fra GitHub, men krever at cron-jobb kjører
Det er anbefalt at dette skjer minst daglig, og forårsaker null nedetid
CRON URL: https://delta.ukm.dev/cron/designbundle/sync_sitemap/

# Flytskjema påmeldingsprosses
**OBS:** beskriver prosessen som om innloggingen er flyttet over til https://id.ukm.no.


![Midlertidig flytskjema](Flytskjema.png?raw=true)
[rediger flytskjema](https://app.diagrams.net/)


# Innslagssidens ulike skjema 
Avhengig av type innslag, må brukeren fylle ut ulike felt. Type innslag deles i 3 overordnede typer. [`Jobbe med`](TYPE-jobbe-med), [`vise frem`](TYPE-vise-frem) eller `person`*.

Hvilke typer som skal ha ulike felt osv, bestemmes fra [UKMNorge\Innslag\Type](https://github.com/UKMNorge/UKMapi/tree/master/Innslag/Typer)-objektene.

*) typen `person` er kun tilgjengelig for arrangement av typen `workshop`, hvor man kun kan melde på og delta som seg selv.

## TYPE: Vise frem
| Type | Navn på innslag | Beskrivelse | Sjanger | Tekniske behov | Tittel | Titler |
| --- | --- | --- | --- | --- | --- | --- |
| Musikk | X | X | X | X | - | X |
| Dans | X | X | X | X | * | X |
| Teater | X | X | X | X | X | - |
| Litteratur | X | X | X | X | X | - |
| Annet på scene | X | X | X | X | - | X |
| Utstilling | X | X | - | - | X | X |
| Film | X | X | X | - | X | - |
| Cosplay | X | X | - | - | - | - |
| Dataspill | X | X | - | - | - | - |
| Matkultur | X | X | - | - | - | X |

*) Dans skal gå fra å støtte flere titler til én tittel, men krever at vi har "dupliser innslag", eller lignende funksjon på plass først.

### Tittel vs Titler
**Nytt i 2020:** Noen typer innslag begrenses til å kun ha én tittel for å forenkle påmeldingsprosessen. 

For utstilling, hvor man i utgangspunktet melder på kun én tittel, må man også få mulighet til å si "dette verket er del av en serie", og da få lov til å legge til flere titler.

### Ulike typer titler
| Type | Navn | Varighet | Selvlaget | Tilleggsfelt |
| --- | --- | --- | --- | --- |
| Musikk | X | X | X | - Tekst / instrumental<br />- Melodi av<br />- Tekst av |
| Dans | X | X | X | - Koreografi av |
| Teater | X | X | X | - Manus av |
| Litteratur | X | X | - | - Ønsker du å lese opp?<br />- Evt medforfattere |
| Annet på scene | X | X | - | - |
| Utstilling | X | - | - | - Type og teknikk |
| Film | X | X | - | - |
| Matkultur | X | - | - | - |

## TYPE: Jobbe med
| Type | Beskrivelse | Funksjoner |
| --- | --- | --- |
| Arrangør | X | X |
| Konferansier | X | - |
| Media | X | X |
