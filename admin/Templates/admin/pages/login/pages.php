<?php

/** Cale relativă admin — fără domeniu .test în asset-uri */
function getCurrentUrl(): string
{
    return '/admin';
}
?>
<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!--favicon-->
    <link rel="icon" href="<?=getCurrentUrl();?>/Templates/admin/assets/images/favicon-32x32.png" type="image/png" />
    <!--plugins-->
    <link href="<?=getCurrentUrl();?>/Templates/admin/assets/plugins/simplebar/css/simplebar.css" rel="stylesheet" />
    <link href="<?=getCurrentUrl();?>/Templates/admin/assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css" rel="stylesheet" />
    <link href="<?=getCurrentUrl();?>/Templates/admin/assets/plugins/metismenu/css/metisMenu.min.css" rel="stylesheet" />
    <!-- loader-->
    <link href="<?=getCurrentUrl();?>/Templates/admin/assets/css/pace.min.css" rel="stylesheet" />
    <script src="<?=getCurrentUrl();?>/Templates/admin/assets/js/pace.min.js"></script>
    <!-- Bootstrap CSS -->
    <link href="<?=getCurrentUrl();?>/Templates/admin/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?=getCurrentUrl();?>/Templates/admin/assets/css/bootstrap-extended.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link href="<?=getCurrentUrl();?>/Templates/admin/assets/css/app.css" rel="stylesheet">
    <link href="<?=getCurrentUrl();?>/Templates/admin/assets/css/icons.css" rel="stylesheet">
    <title>Rocker - Bootstrap 5 Admin Dashboard Template</title>
</head>

<body class="">
<!--wrapper-->
<div class="wrapper">
    <div class="section-authentication-cover">
        <div class="">
            <div class="row g-0">

                <div class="col-12 col-xl-7 col-xxl-8 auth-cover-left align-items-center justify-content-center d-none d-xl-flex">

                    <div class="card shadow-none bg-transparent shadow-none rounded-0 mb-0">
                        <div class="card-body">
                            <img src="<?=getCurrentUrl();?>/Templates/admin/assets/images/login-images/login-cover.svg" class="img-fluid auth-img-cover-login" width="650" alt=""/>
                        </div>
                    </div>

                </div>

                <div class="col-12 col-xl-5 col-xxl-4 auth-cover-right align-items-center justify-content-center">
                    <div class="card rounded-0 m-3 shadow-none bg-transparent mb-0">
                        <div class="card-body p-sm-5">
                            <div class="">
                                <div class="mb-3 text-center">
                                    <img src="<?= getCurrentUrl(); ?>/Templates/admin/assets/images/logo-icon.png" width="60" alt="">
                                </div>
                                <div class="text-center mb-4">
                                    <h5 class="mb-1">Galac System Group</h5>
                                    <p class="mb-0 text-muted">Acces restricționat</p>
                                </div>

                                <div class="form-body">
                                    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                                        <i class="bx bxs-lock fs-4 mt-1"></i>
                                        <div>
                                            <strong>Nu ai încă acces la proiect.</strong><br>
                                            Contul tău este în curs de aprobare. Vei fi anunțat când primești drepturile necesare și vei putea fi logat(ă) în sistem.
                                        </div>
                                    </div>

                                    <!-- (opțional) afișează cine e conectat, dacă există sesiune -->
                                    <?php if (!empty($_SESSION['pending_email'])): ?>
                                        <div class="mb-3 text-center small text-muted">
                                            Email înregistrat: <strong><?= htmlspecialchars($_SESSION['pending_email']); ?></strong>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-grid gap-2">

                                        <a href="mailto:support@worldwinner.online" class="btn btn-outline-primary">
                                            <i class="bx bx-help-circle me-1"></i> Contactează administratorul
                                        </a>
                                        <a href="/public/login" class="btn btn-outline-danger">
                                            <i class="bx bx-log-out me-1"></i> Delogare / Schimbă contul
                                        </a>
                                    </div>

                                    <hr class="my-4">

                                    <div class="text-center small text-muted">
                                        Roluri în ierarhie: <strong>Ambasador Suprem</strong> → <strong>Ambasador Regional</strong> → <strong>Manager</strong> → <strong>Executiv</strong> → <strong>Vizitator</strong>.
                                        Accesul tău va fi configurat în funcție de rolul asignat.
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!--end row-->
        </div>
    </div>
</div>
<!--end wrapper-->
<!-- Bootstrap JS -->
<script src="<?=getCurrentUrl();?>/Templates/admin/assets/js/bootstrap.bundle.min.js"></script>
<!--plugins-->
<script src="<?=getCurrentUrl();?>/Templates/admin/assets/js/jquery.min.js"></script>
<script src="<?=getCurrentUrl();?>/Templates/admin/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="<?=getCurrentUrl();?>/Templates/admin/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="<?=getCurrentUrl();?>/Templates/admin/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<!--Password show & hide js -->
<script>
    $(document).ready(function () {
        $("#show_hide_password a").on('click', function (event) {
            event.preventDefault();
            if ($('#show_hide_password input').attr("type") == "text") {
                $('#show_hide_password input').attr('type', 'password');
                $('#show_hide_password i').addClass("bx-hide");
                $('#show_hide_password i').removeClass("bx-show");
            } else if ($('#show_hide_password input').attr("type") == "password") {
                $('#show_hide_password input').attr('type', 'text');
                $('#show_hide_password i').removeClass("bx-hide");
                $('#show_hide_password i').addClass("bx-show");
            }
        });
    });
</script>
<!--app JS-->
<script src="<?=getCurrentUrl();?>/Templates/admin/assets/js/app.js"></script>
</body>

<script>'undefined'=== typeof _trfq || (window._trfq = []);'undefined'=== typeof _trfd && (window._trfd=[]),_trfd.push({'tccl.baseHost':'secureserver.net'},{'ap':'cpsh-oh'},{'server':'p3plzcpnl509132'},{'dcenter':'p3'},{'cp_id':'10399385'},{'cp_cl':'8'}) // Monitoring performance to make your website faster. If you want to opt-out, please contact web hosting support.</script><script src='https://img1.wsimg.com/traffic-assets/js/tccl.min.js'></script></html>