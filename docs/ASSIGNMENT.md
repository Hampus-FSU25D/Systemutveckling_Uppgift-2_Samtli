# Uppgiftskrav

Samtli är ett PHP-projekt för en kursuppgift där SQL, säker datahantering, behörigheter, arkitektur och versionshistorik bedöms.

## G-krav

- Databasen ska vara SQL.
- Alla applikationssidor ska vara PHP.
- React är inte tillåtet.
- JavaScript-ramverk får inte användas.
- Mindre mängder vanlig JavaScript får användas för att förbättra användarupplevelsen.
- Data ska hanteras säkert.
- En användare får aldrig få tillgång till information som användaren inte är behörig till.
- En användare kan registrera ett konto.
- Ett användarkonto sparar förnamn, efternamn, e-post och lösenordshash.
- En inloggad användare kan skapa en grupp.
- En grupp har ett namn som beskriver vad som diskuteras.
- En gruppmedlem kan starta en diskussion.
- En diskussion innehåller ämne och första inlägg.
- Alla gruppmedlemmar kan svara i diskussioner.
- En användare kan se grupper som användaren ännu inte är medlem i.
- En användare kan ansöka om medlemskap i en sådan grupp.
- Medlemsansökningar kan godkännas enligt rollreglerna.

## VG-krav

VG-implementationen är första huvudmålet.

- En användare kan ha rollen `member` eller `administrator` i en grupp.
- Roller är per grupp, inte globala.
- Endast administratörer får godkänna medlemsansökningar.
- Administratörer får ändra roll för andra gruppmedlemmar.
- Administratörer får skapa inbjudningslänkar.
- En giltig inbjudan går förbi den vanliga godkännandeprocessen.
- Inbjudan är en hot link.
- Inbjudan kan bara användas en gång.
- Inbjudan upphör att gälla efter 24 timmar.

Om G-kravet om att medlemmar kan godkänna ansökningar krockar med VG-regeln ska Samtli använda den striktare regeln: endast gruppadministratörer får godkänna medlemsansökningar.

## Inlämning

Den färdiga uppgiften ska innehålla:

- skärmbild på startsidan
- skärmbild på en gruppdiskussion
- skärmbild som visar svar i en diskussion
- genomgångsvideo med voice-over som visar kontoskapande, medlemsansökan och hur en diskussion startas

## Kursmål

Projektet ska visa:

- programstruktur för informationshantering
- informationsflöde
- användare och behörigheter
- system- och kodbasdesign baserad på arkitekturprinciper
- API-testning med Postman
