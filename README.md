### Integrasjonen:

Henter og serialiserer ordre fra server. Jeg leste av pdf’en at det kan eksistere flere ordre pr fil, dette er på plass.

### WP plugin:

Mottar data fra integrasjonen. Den mottar et array, så den vil kunne motta et potensielt stort antall ordre.
Alle ordre får et po_number på seg. Dette er internreferansen som kommer fra integrasjonen. Dette blir brukt til å validere om ordren er opprettet eller ei. Det er ikke støtte for å endre ordre etter at den er opprettet.
Pluginen henter priser basert på en kundeKey som kommer fra server. Vedlikehold av discount_keys skjer i plugin pr nå. Om kunde_key ikke finnes for et gitt produkt, settes standardpris, samme med om kunden ikke har en kundeprisavtale.
Pluginen avviser pr nå produkter, som har en ugyldig sku.

### Veien videre:

Det mangler (3 minutters jobb) å sende dataen fra integrasjonen til plugin. Dette avventes til plugin er ferdig, så jeg slipper å reserialisere dataene flere ganger, i tilfelle endringer.
Det kan potensielt håndteres annerledes hvordan vi gjør det med ugyldige sku. Forslag kan være å se om det kan lages bare et custom line item, så i hvertfall sku + quanity blir bevart på ordrelinjen.
Det settes ikke en kundeid på ordren, så alt kommer som guest nå. Tenker det er bedre at sier hvordan du vil ha det.
