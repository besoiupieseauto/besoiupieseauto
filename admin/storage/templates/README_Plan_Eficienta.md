# Plan eficienta financiara — Besoiu Piese Auto

Fisier Excel: **`Plan_Eficienta_Besoiu_Piese_Auto.xlsx`**

## Unde în admin

Pagină dedicată: **`/admin/planner`** (meniu lateral → **Planner**, secțiunea Analiză).

Dashboard marketing rămâne doar pentru căutări și grafice — calculele financiare sunt pe pagina Planner.

### Foaia 1 — Plan eficienta

| Etapa | Ce completezi | Ce calculeaza |
|-------|----------------|---------------|
| **1 — Cheltuieli** | Coloana **B** (lunar): salarii, chirie, amortizare, IT, marketing etc. | Coloana **C** = B × 3 (trimestrial) |
| **2 — Venit actual** | Coloana **B** (lunar): magazin, marketplace, B2B | Coloana **C** = B × 3 |
| **3 — Scenariu previzibil** | B26=1000 user, B27=5%, B28=141,23 RON | Comenzi, venit/luna, venit/trimestru |
| **4 — Rezultat** | — | Acopera cheltuielile? DA/NU, deficit/surplus |
| **5 — Investitii** | Coloana **B**: elaborare platforma, promovare | Total de recuperat |
| **6 — Break-even** | — | Cati trimestre pana recuperezi investitiile |

### Foaia 2 — Scenarii

Comparatie automata:
- doar venit stabil
- stabil + 1000 utilizatori (5%, 141,23 RON)
- doar scenariul marketing (fara venit stabil)

## Exemplu implicit (scenariu marketing)

- 1000 utilizatori × 5% = **50 comenzi**
- 50 × 141,23 RON = **7.061,50 RON / luna**
- Trimestrial scenariu = **21.184,50 RON** (daca atingi tinta lunar)

## Regenerare fisier

```powershell
powershell -ExecutionPolicy Bypass -File admin\tools\generate_efficiency_plan_xlsx.ps1
```

sau (daca PHP are ZipArchive):

```bash
php admin/tools/generate_efficiency_plan_xlsx.php
```

Valorile din coloana B sunt exemple — inlocuieste-le cu cifrele reale ale firmei.
