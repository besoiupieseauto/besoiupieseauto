README – Tree categorii pentru programator
========================================

REGULĂ CRITICĂ
--------------
Coloana "Nume" de pe frunză (ultimul nivel) = ART_NAME EXACT din nume produse.xlsx / baza de date.
Programatorul împerechează: categorie.Nume == produs.ART_NAME (match exact).
NU folosiți Nume_afisare pentru împerechere!

Livrabile
---------
1. categorii_tree_final.xlsx
   - categorii: ID, Nume (cheie), Nume_afisare (pt. UI), Parinte_ID, Nivel, Ordine, slug, Cale
   - mapare_produse: ART_NAME -> ID_categorie (Nume_categorie == ART_NAME)
   - audit_frunze: verificare Frunza_egal_ART_NAME = DA
   - categorii_secundare: produse care trebuie să apară ȘI într-o a doua categorie
   - sinonime_cautare: termeni populari -> produsul/categoria țintă (pt. căutarea internă)
2. categorii_tree_vizual.txt – arbore vizual cu [ID]

Corecții taxonomie aplicate
---------------------------
1. EVAP separat de AdBlue (filtru și supapă carbon activ)
2. Sistemul de aer secundar mutat la Evacuare
3. Volanta și șuruburile mutate la Ambreiaj
4. Rulare separată în "Roți și butuci"; planetarele și cardanul sunt la Transmisie
5. Grupa redenumită "Climatizare și încălzire", cu familie pentru încălzirea habitaclului
6. Setul de reparație macara geam este la Caroserie
7. Cilindrul de închidere/contact este la Electrică și senzori
8. Perna de aer a podelei portbagajului este la Capotă, hayon și portbagaj
9. Cablul și pedala de accelerație sunt în familia Comandă accelerație

Coloana Nume_afisare (nou)
--------------------------
- "Nume" = ART_NAME exact (cheie tehnică, nu se modifică)
- "Nume_afisare" = numele afișat pe site: diacritice + greșeli de tipar corectate
  (ex: "Toba esapamet intermediara" -> "Tobă eșapament intermediară")
- Se poate edita liber în Excel fără să strice împerecherea.

Categorii secundare (nou)
-------------------------
- Sheet "categorii_secundare": produsul rămâne legat de categoria principală,
  dar se afișează și în categoria secundară (ex: senzorul ABS apare și la Frânare).
- Implementare: tabelă many-to-many produs-categorie sau câmp "categorii_extra".

Sinonime căutare (nou)
----------------------
- Sheet "sinonime_cautare": când clientul caută "aeroterma", motorul de căutare
  trebuie să întoarcă produsul țintă ("Ventilator, habitaclu") / categoria lui.
- Implementare: dicționar de sinonime în motorul de căutare al site-ului.

Convenții
---------
- Parinte_ID = 0 => grupă principală
- Numele de pe frunză NU se traduce / NU se normalizează
- Un ART_NAME = o frunză

Import
------
1. Insert categorii după Parinte_ID / Nivel
2. La produs: category_id = mapare pe ART_NAME exact
   (sau: găsește categoria unde Nume == ART_NAME și e frunză)
3. Adaugă legăturile suplimentare din categorii_secundare
4. Încarcă sinonimele în căutare

Statistici
----------
- Categorii: 629
- Produse mapate: 527 / 527
- Frunze == ART_NAME: 527
- Fallback: 0
- Frunze goale: 0
- Categorii secundare: 25
- Sinonime: 46

Grupe principale
----------------
1 Filtre
2 Motor
3 Alimentare combustibil
4 Admisie aer și turbo
5 Evacuare
6 Răcire motor
7 Aprindere
8 Transmisie
9 Ambreiaj
10 Frânare
11 Direcție
12 Suspensie
13 Roți și butuci
14 Electrică și senzori
15 Iluminare
16 Climatizare și încălzire
17 Caroserie
18 Ștergere și spălare
