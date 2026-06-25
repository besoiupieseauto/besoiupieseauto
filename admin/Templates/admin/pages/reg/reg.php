<?php

function getCurrentUrl(): string
{
    return '/admin';
}
// Nu apelăm logoutSimple aici pentru că utilizatorul s-ar putea să vrea doar să creeze un cont nou
?>

<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Robot pieseauto.ro — Înregistrare Cont Nou</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        body{ font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial; background:linear-gradient(180deg,#fff,rgba(2,6,23,.02)); min-height: 100vh; }
        .cardx{ border:1px solid rgba(15,23,42,.10); border-radius:24px; background:#fff; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .muted{ color:rgba(15,23,42,.60); }
        .btn-accent{ background:#ff6a00; border-color:#ff6a00; color:#fff; font-weight:900; border-radius:14px; padding: 12px; transition: all 0.3s; }
        .btn-accent:hover{ background:#e65f00; transform: translateY(-1px); }
        .btn-ghost{ border:1px solid rgba(15,23,42,.14); background:transparent; border-radius:14px; font-weight:900; }
        .brand{ font-weight:950; font-size:18px; }
        .badge-soft{ display:inline-flex; align-items:center; gap:8px; padding:6px 12px; border-radius:999px; border:1px solid rgba(15,23,42,.12); background:rgba(2,6,23,.02); font-weight:900; font-size:12px;}
        .form-control{ border-radius: 12px; padding: 10px 15px; border: 1px solid rgba(15,23,42,.15); }
        .form-control:focus{ border-color: #ff6a00; box-shadow: 0 0 0 0.25 cold-rem rgba(255, 106, 0, 0.1); }
    </style>
</head>

<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">

            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <div class="brand">Creează un cont de administrator</span></div>
           
                </div>
            </div>

            <div class="cardx p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <div style="font-weight:950; font-size:22px;">Înregistrare</div>
                        <div class="muted">Completează datele de mai jos.</div>
                    </div>
                    <i class="bi bi-person-plus text-primary fs-3"></i>
                </div>

                <form id="registerForm"
                      data-endpoint="/admin/addusersadd"
                      data-method="POST"
                      class="row g-3">

                    <div class="col-12">
                        <label class="form-label fw-semibold">Nume Complet</label>
                        <input type="text" class="form-control" name="fullname" placeholder="Ex: Ion Popescu" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Username / Email</label>
                        <input type="text" class="form-control" name="login" placeholder="Utilizator sau email" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Parolă</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Minim 8 caractere" required>
                            <button class="btn btn-ghost" type="button" id="btnTogglePass">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Rol / nivel permisiuni</label>
                        <select class="form-control" name="role" required>
                            <option value="manager">Manager — acces operațional complet</option>
                            <option value="regional_ambassador">Regional ambassador</option>
                            <option value="executive">Executive — rapoarte și vizualizare</option>
                            <option value="operator">Operator — acces limitat</option>
                            <option value="super_ambassador">Super ambassador — acces total (doar pentru admin principal)</option>
                        </select>
                        <div class="form-text muted small mt-1">Meniu și modulele vizibile depind de rol (tabela <code>role_nav</code>).</div>
                    </div>

                    <div class="col-12 mt-4">
                        <button class="btn btn-accent w-100" type="submit" id="btnSubmit">
                            <i class="bi bi-check-circle-fill me-2"></i> Creează Contul
                        </button>
                    </div>

                    <div class="col-12 text-center mt-3">
                        <p class="muted small">Ai deja un cont? <a href="/admin/login" class="text-decoration-none fw-bold" style="color:#ff6a00;">Loghează-te aici</a></p>
                    </div>
                </form>
            </div>

            <div id="responseStatus" class="mt-3 text-center d-none">
                <div class="badge-soft py-2 px-3 w-100 justify-content-center" id="statusContent"></div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('#registerForm');
        const btnSubmit = document.querySelector('#btnSubmit');
        const password = document.querySelector('#password');
        const toggle = document.querySelector('#btnTogglePass');
        const respStatus = document.querySelector('#responseStatus');
        const statusContent = document.querySelector('#statusContent');

        /* TOGGLE VISIBILITATE PAROLĂ */
        if (toggle && password) {
            toggle.addEventListener('click', () => {
                const isText = password.type === 'text';
                password.type = isText ? 'password' : 'text';
                toggle.innerHTML = isText ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
            });
        }

        /* SUBMIT FORMULAR AJAX */
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Pregătire UI
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Se procesează...';
            respStatus.classList.add('d-none');

            const formData = new FormData(form);
            const data = {
                type_product: 'add' // Parametrul cerut de backend-ul tău pentru înregistrare
            };

            formData.forEach((value, key) => {
                data[key] = value.trim();
            });

            try {
                const response = await fetch(form.dataset.endpoint, {
                    method: form.dataset.method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const rawText = await response.text();
                let result;

                // Încercăm să extragem JSON-ul (folosind logica de curățare din codul tău)
                try {
                    // Căutăm prima acoladă în caz că serverul dă și alte mesaje (noise)
                    const startJson = rawText.indexOf('{');
                    const cleanJson = startJson >= 0 ? rawText.substring(startJson) : rawText;
                    result = JSON.parse(cleanJson);
                } catch (e) {
                    throw new Error("Eroare la procesarea răspunsului serverului.");
                }

                if (result.success === true || result.success === 1 || result.success === "true") {
                    // SUCCES
                    statusContent.innerHTML = '<i class="bi bi-check-circle text-success"></i> ' + (result.message || "Cont creat cu succes!");
                    respStatus.classList.remove('d-none');

                    // Redirecționare după 1.5 secunde
                    setTimeout(() => {
                        window.location.href = "/admin/login";
                    }, 1500);
                } else {
                    // EROARE DIN SERVER
                    throw new Error(result.message || "A apărut o eroare la înregistrare.");
                }

            } catch (err) {
                statusContent.innerHTML = '<i class="bi bi-exclamation-triangle text-danger"></i> ' + err.message;
                respStatus.classList.remove('d-none');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Reîncearcă';
            }
        });
    });
</script>

</body>
</html>