<?php require_once __DIR__ . '/auth_guard.php'; ?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Selector TecDoc - Piese Auto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .single-select { margin-bottom: 15px; }
        select { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ccc; background-color: #fff; }
        select:disabled { background-color: #f9f9f9; cursor: not-allowed; }
        .card-selector { max-width: 500px; margin: auto; }
        label { font-size: 12px; color: #d01818; text-transform: uppercase; margin-bottom: 5px; }

        /* Stil adaugat pentru tabelul de catalog */
        #catalogContainer { display: none; margin-top: 40px; background: white; padding: 20px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .img-piesa { width: 60px; height: 60px; object-fit: contain; background: #f8f9fa; border-radius: 5px; }
    </style>
</head>
<body class="bg-light p-5">

<div class="container card-selector bg-white p-4 shadow-sm rounded-4">
    <h4 class="mb-4 fw-bold text-center">Selector Piese Auto</h4>

    <form id="tecdocForm">
        <div class="single-select">
            <label class="fw-bold">1. Alege Marca</label>
            <select id="select_marca" name="marca">
                <option value="0">MARCA ...</option><option value="2">ALFA ROMEO</option><option value="881">ASTON MARTIN</option><option value="5">AUDI</option><option value="815">BENTLEY</option><option value="16">BMW</option><option value="788">BUGATTI</option><option value="819">CADILLAC</option><option value="138">CHEVROLET</option><option value="20">CHRYSLER</option><option value="21">CITROEN</option><option value="4896">CUPRA</option><option value="139">DACIA</option><option value="185">DAEWOO</option><option value="24">DAF</option><option value="25">DAIHATSU</option><option value="29">DODGE</option><option value="700">FERRARI</option><option value="35">FIAT</option><option value="36">FORD</option><option value="45">HONDA</option><option value="1506">HUMMER</option><option value="183">HYUNDAI</option><option value="1526">INFINITI</option><option value="54">ISUZU</option><option value="55">IVECO</option><option value="56">JAGUAR</option><option value="882">JEEP</option><option value="184">KIA</option><option value="63">LADA</option><option value="701">LAMBORGHINI</option><option value="64">LANCIA</option><option value="1820">LAND ROVER</option><option value="842">LEXUS</option><option value="69">MAN</option><option value="771">MASERATI</option><option value="2164">MAYBACH</option><option value="72">MAZDA</option><option value="74">MERCEDES-BENZ</option><option value="75">MG</option><option value="1523">MINI</option><option value="77">MITSUBISHI</option><option value="80">NISSAN</option><option value="84">OPEL</option><option value="88">PEUGEOT</option><option value="774">PONTIAC</option><option value="92">PORSCHE</option><option value="778">PROTON</option><option value="93">RENAULT</option><option value="694">RENAULT TRUCKS</option><option value="705">ROLLS-ROYCE</option><option value="95">ROVER</option><option value="99">SAAB</option><option value="103">SCANIA</option><option value="104">SEAT</option><option value="106">SKODA</option><option value="1138">SMART</option><option value="175">SSANGYONG</option><option value="107">SUBARU</option><option value="109">SUZUKI</option><option value="3328">TESLA</option><option value="111">TOYOTA</option><option value="120">VOLVO</option><option value="121">VW</option></select>
            </select>
        </div>

        <div class="single-select">
            <label class="fw-bold">2. Alege Modelul</label>
            <select id="model_marca" name="model" disabled>
                <option value="0">MODEL ...</option>
            </select>
        </div>

        <div class="single-select">
            <label class="fw-bold">3. Alege Motorizarea / Puterea</label>
            <select id="motorizari" name="motorizare" disabled>
                <option value="0">MOTORIZARE ...</option>
            </select>
        </div>

        <button type="submit" class="btn btn-danger w-100 fw-bold mt-3 py-3" id="btnSearch" disabled>
            CAUTĂ PIESELE ACUM
        </button>
    </form>
</div>

<div class="container" id="catalogContainer">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold m-0"><i class="bi bi-box-seam text-danger me-2"></i>Catalog Piese Identificate</h5>
        <span id="piesaCount" class="badge bg-secondary">0 piese</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light small text-uppercase">
            <tr>
                <th>Poză</th>
                <th>Denumire / Brand</th>
                <th>Cod Piesă</th>
                <th>Status</th>
                <th class="text-end">Acțiune</th>
            </tr>
            </thead>
            <tbody id="catalogBody">
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="modalProduse" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="modalTitle">Produse Categorie</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <div id="loadingProduse" class="text-center p-5" style="display:none;">
                    <div class="spinner-border text-danger" role="status"></div>
                    <p class="mt-2 text-muted">Se caută piesele în catalog...</p>
                </div>
                <div class="row g-3" id="listaProduseContainer">
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const apiPath = 'tecdoc_proxy.php'; // Scriptul tău care apelează TecDoc

    // 1. Când alegi MARCA -> aducem MODELELE
    document.getElementById('select_marca').onchange = async (e) => {
        const manuId = e.target.value;
        const modelSelect = document.getElementById('model_marca');

        if(manuId == 0) return;

        modelSelect.disabled = false;
        modelSelect.innerHTML = '<option>Se încarcă...</option>';

        try {
            const response = await fetch(`${apiPath}?action=get_models&manuId=${manuId}`);
            const data = await response.json();

            modelSelect.innerHTML = '<option value="0">Alege Modelul...</option>';

            // CORECȚIE: API-ul returnează un obiect, array-ul este în data.models
            if (data && data.models && Array.isArray(data.models)) {
                data.models.forEach(m => {
                    let opt = document.createElement('option');
                    opt.value = m.modelId; // ID-ul de model real (ex: 278)
                    // Opțional: Adăugăm și anii de producție pentru o selecție mai ușoară
                    const yearFrom = m.modelYearFrom ? m.modelYearFrom.substring(0, 4) : '';
                    const yearTo = m.modelYearTo ? m.modelYearTo.substring(0, 4) : 'Prezent';

                    opt.innerHTML = `${m.modelName} (${yearFrom} - ${yearTo})`;
                    modelSelect.appendChild(opt);
                });
            } else {
                modelSelect.innerHTML = '<option value="0">Nu s-au găsit modele</option>';
            }

        } catch (error) {
            console.error("Eroare la încărcarea modelelor:", error);
            modelSelect.innerHTML = '<option value="0">Eroare la server</option>';
        }

        modelSelect.disabled = false;
    };

    // 2. Când alegi MODELUL -> aducem MOTORIZĂRILE
    document.getElementById('model_marca').addEventListener('change', async function() {
        const modelId = this.value;
        const motorSelect = document.getElementById('motorizari');
        const btn = document.getElementById('btnSearch');

        motorSelect.innerHTML = '<option value="0">Se încarcă motorizările...</option>';
        motorSelect.disabled = true;
        btn.disabled = true;

        if (modelId == "0") return;

        try {
            const response = await fetch(`${apiPath}?action=get_vehicles&modelId=${modelId}`);
            const data = await response.json();

            motorSelect.innerHTML = '<option value="0">MOTORIZARE / PUTERE ...</option>';

            // CORECȚIE LOGICĂ:
            // Verificăm dacă primim o listă (Array) sau un singur obiect (Details)

            if (data.vehicles && Array.isArray(data.vehicles)) {
                // Caz 1: Primim o listă reală de mașini
                data.vehicles.forEach(v => {
                    let opt = document.createElement('option');
                    opt.value = v.carId;
                    opt.innerHTML = `${v.typeName} (${v.powerPs} CP / ${v.powerKw} KW) - ${v.fuelType}`;
                    motorSelect.appendChild(opt);
                });
            } else if (data.vehicleTypeDetails) {
                // Caz 2: Primim obiectul tău curent (o singură mașină)
                const v = data.vehicleTypeDetails;
                let opt = document.createElement('option');
                opt.value = modelId; // Folosim modelId dacă carId nu e prezent în detalii
                opt.innerHTML = `${v.typeEngineName} (${v.powerPs} CP / ${v.powerKw} KW) - ${v.fuelType}`;
                motorSelect.appendChild(opt);
            } else {
                console.error("Format date necunoscut", data);
            }

            motorSelect.disabled = false;
            btn.disabled = false; // Activăm butonul de căutare
        } catch (error) {
            console.error("Eroare la încărcarea motorizărilor:", error);
            motorSelect.innerHTML = '<option value="0">Eroare la server</option>';
        }
    });

    // 3. Când se alege Motorizarea -> activăm butonul
    document.getElementById('motorizari').addEventListener('change', function() {
        document.getElementById('btnSearch').disabled = (this.value == "0");
    });

    // 4. ADAUGAT: Modificat pentru a afișa tabelul în aceeași pagină
    document.getElementById('tecdocForm').onsubmit = async (e) => {
        e.preventDefault();
        const carId = document.getElementById('motorizari').value;
        const catalogBody = document.getElementById('catalogBody');
        const catalogContainer = document.getElementById('catalogContainer');
        const btn = document.getElementById('btnSearch');

        btn.innerText = "SE CAUTĂ CATEGORIILE...";
        btn.disabled = true;
        catalogContainer.style.display = 'block';
        catalogBody.innerHTML = '<tr><td colspan="5" class="text-center py-5">Se interoghează structura TecDoc...</td></tr>';

        try {
            const response = await fetch(`${apiPath}?action=get_parts&carId=${carId}`);
            const data = await response.json();

            console.log("Date primite:", data);
            catalogBody.innerHTML = '';

            // Verificăm dacă avem obiectul 'categories'
            if (data.categories && typeof data.categories === 'object') {
                const catKeys = Object.keys(data.categories);
                document.getElementById('piesaCount').innerText = catKeys.length + " categorii sistem";

                catKeys.forEach(id => {
                    const category = data.categories[id];

                    // Generăm un rând pentru fiecare categorie principală
                    const row = `
                    <tr>
                        <td><div class="p-2 bg-light rounded text-center fw-bold text-danger">${id}</div></td>
                        <td>
                            <div class="fw-bold">${category.text}</div>
                            <div class="small text-muted">Sistem vehicul</div>
                        </td>
                        <td><span class="text-muted small">${Object.keys(category.children || {}).length} sub-categorii</span></td>
                        <td><span class="badge bg-info text-dark">Disponibil</span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-danger" onclick="incarcaProduseCategorie('${id}', '${carId}')">
                                Vezi Produse <i class="bi bi-arrow-right"></i>
                            </button>
                        </td>
                    </tr>
                `;
                    catalogBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                catalogBody.innerHTML = '<tr><td colspan="5" class="text-center py-5">Nu s-au găsit categorii disponibile.</td></tr>';
            }
        } catch (error) {
            console.error("Eroare la procesare JSON:", error);
            catalogBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-5">Eroare la conexiunea cu baza de date.</td></tr>';
        } finally {
            btn.innerText = "CAUTĂ PIESELE ACUM";
            btn.disabled = false;
        }
    };

    async function incarcaProduseCategorie(categoryId, carId) {
        const modalElement = document.getElementById('modalProduse');
        const container = document.getElementById('listaProduseContainer');
        const loader = document.getElementById('loadingProduse');
        const title = document.getElementById('modalTitle');

        // Deschidem modalul
        const myModal = new bootstrap.Modal(modalElement);
        myModal.show();

        // Resetăm interfața
        container.innerHTML = '';
        loader.style.display = 'block';
        title.innerText = `Produse Categorie: ${categoryId}`;

        try {
            // Apel către proxy-ul tău RapidAPI
            const response = await fetch(`tecdoc_proxy.php?action=get_articles&carId=${carId}&nodeId=${categoryId}`);
            const data = await response.json();

            loader.style.display = 'none';

            if (!data || data.length === 0) {
                container.innerHTML = '<div class="col-12 text-center p-5"><h5>Nu am găsit produse pentru această categorie.</h5></div>';
                return;
            }

            // Generăm cartelele pentru fiecare piesă găsită
            data.forEach(item => {
                const card = `
                <div class="col-md-4 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden">
                        <div style="height: 150px; background: #eee; display: flex; align-items: center; justify-content: center;">
                            <img src="${item.s3image || 'https://via.placeholder.com/150?text=Fara+Foto'}"
                                 class="img-fluid" style="max-height: 100%;"
                                 onerror="this.src='https://via.placeholder.com/150?text=Piesa+Auto'">
                        </div>
                        <div class="card-body p-3">
                            <div class="small text-danger fw-bold">${item.brandName}</div>
                            <div class="fw-bold mb-1" style="font-size: 14px; height: 40px; overflow: hidden;">${item.articleName}</div>
                            <div class="text-muted small mb-2">Cod: ${item.articleNumber}</div>
                            <button class="btn btn-sm btn-dark w-100" onclick="alert('Cod OEM: ${item.articleNumber}')">Vezi Detalii</button>
                        </div>
                    </div>
                </div>
            `;
                container.insertAdjacentHTML('beforeend', card);
            });

        } catch (error) {
            loader.style.display = 'none';
            container.innerHTML = `<div class="alert alert-danger">Eroare la încărcare: ${error.message}</div>`;
        }
    }
</script>

</body>
</html>