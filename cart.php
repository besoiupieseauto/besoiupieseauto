<?php
require_once __DIR__ . '/system/page-init.php';
require_once __DIR__ . '/system/besoiu-assets.php';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coș de cumpărături — Besoiu Piese Auto</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php besoiu_render_styles('cart', ['assets/css/cart-page.css']); ?>
</head>
<body>
<div class="page">

<?php include_once 'system/header.php'; ?>

<main id="main-content">
    <section class="cart-page">
        <div class="container">

            <!-- ══ STEPPER ══ -->
            <div class="stepper" id="checkout-stepper" aria-label="Pași finalizare comandă">
                <div class="step active" data-checkout-step="1">
                    <div class="step-circle"><i class="fa-solid fa-check"></i></div>
                    <span class="step-label">Coș</span>
                </div>
                <div class="step-line active"></div>
                <div class="step" data-checkout-step="2">
                    <div class="step-circle">2</div>
                    <span class="step-label">Finalizare</span>
                </div>
                <div class="step-line"></div>
                <div class="step" data-checkout-step="3">
                    <div class="step-circle">3</div>
                    <span class="step-label">Confirmare</span>
                </div>
            </div>
            <p class="checkout-flow-hint" id="checkout-flow-hint">Pasul 1: verifică produsele din coș, apoi continuă la finalizare.</p>

            <div class="checkout-success is-hidden" id="checkout-success-panel" role="status" aria-live="polite">
                <div class="checkout-success-icon"><i class="fa-solid fa-circle-check"></i></div>
                <h3 id="checkout-success-title">Comanda a fost trimisă cu succes.</h3>
                <p>Mulțumim! Echipa noastră te va contacta dacă sunt necesare clarificări.</p>
            </div>

            <!-- ══ LAYOUT ══ -->
            <div class="cart-layout">

                <!-- LEFT: PRODUCTS -->
                <div>
                    <div class="products-card">
                        <div class="products-header">
                            <h2>Produsele tale</h2>
                            <span class="products-badge" id="cart-badge">0 produse</span>
                        </div>

                        <div class="cart-items-list" id="cart-items-list">
                            <div class="cart-loading">
                                <div class="cart-loading-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                                <p>Se încarcă coșul...</p>
                            </div>
                        </div>

                        <div class="cart-continue-wrap is-hidden" id="cart-continue-wrap">
                            <button type="button" class="btn-continue-checkout" id="btn-continue-checkout">
                                Continuă la finalizare <i class="fa-solid fa-arrow-down"></i>
                            </button>
                        </div>

                        <div class="coupon-bar">
                            <div class="coupon-wrap">
                                <input type="text" id="cart-coupon-code" placeholder="Introdu codul promoțional...">
                                <button type="button" id="cart-coupon-apply">APLICĂ</button>
                            </div>
                            <span class="coupon-hint" id="cart-coupon-hint">Ai un cod promoțional? Introdu-l aici (ex: BESOIU10)</span>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: SUMMARY -->
                <aside class="cart-sidebar" id="checkout-panel">
                    <div class="summary-card">
                        <div class="summary-top">
                            <h3>Sumar comandă</h3>
                            <div class="summary-top-icon"><i class="fa-solid fa-bag-shopping"></i></div>
                        </div>
                        <div class="summary-body">
                            <table class="table-totals">
                                <tbody>
                                    <tr>
                                        <td>Subtotal</td>
                                        <td data-cart-subtotal>0.00 RON</td>
                                    </tr>
                                    <tr data-cart-discount-row class="is-hidden">
                                        <td>Reducere cupon</td>
                                        <td data-cart-discount>- 0.00 RON</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa-solid fa-truck summary-label-icon"></i>Livrare</td>
                                        <td>Gratuit</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td>TOTAL</td>
                                        <td data-cart-total>0.00 RON</td>
                                    </tr>
                                </tfoot>
                            </table>

                            <div class="delivery-title"><i class="fa-solid fa-box"></i> Metoda de livrare</div>
                            <p class="checkout-note" id="checkout-location-note">Alege unde vrei să primești comanda. Metodele de plată disponibile se actualizează automat.</p>
                            <div class="delivery-grid">
                                <label class="delivery-opt active" data-ship="ridicare_locala">
                                    <input type="radio" name="shipping_method" value="ridicare_locala" checked>
                                    <span class="d-radio"></span>
                                    <span class="d-icon"><i class="fa-solid fa-store"></i></span>
                                    <span>
                                        <span class="d-label">Ridicare locală</span>
                                        <span class="d-hint">Timișoara, magazin</span>
                                    </span>
                                </label>
                                <label class="delivery-opt" data-ship="tarif_fix">
                                    <input type="radio" name="shipping_method" value="tarif_fix">
                                    <span class="d-radio"></span>
                                    <span class="d-icon"><i class="fa-solid fa-truck-fast"></i></span>
                                    <span>
                                        <span class="d-label">Curier rapid</span>
                                        <span class="d-hint">Livrare acasă</span>
                                    </span>
                                </label>
                            </div>

                            <div class="delivery-title"><i class="fa-solid fa-credit-card"></i> Metoda de plată</div>
                            <div class="payment-grid" id="payment-methods-grid"></div>

                            <form id="cart-shipping-form" onsubmit="return false;">
                                <div class="client-title"><i class="fa-solid fa-address-card"></i> Date livrare</div>
                                <label class="s-label" for="cart-client-name">Nume și prenume</label>
                                <input type="text" class="s-input" id="cart-client-name" name="client_name" placeholder="Nume și prenume" autocomplete="name" required>
                                <div class="s-row">
                                    <div class="s-field">
                                        <label class="s-label" for="cart-phone">Telefon</label>
                                        <input type="tel" class="s-input" id="cart-phone" name="phone" placeholder="07xx xxx xxx" inputmode="tel" autocomplete="tel" required>
                                    </div>
                                    <div class="s-field">
                                        <label class="s-label" for="cart-email">Email</label>
                                        <input type="email" class="s-input" id="cart-email" name="email" placeholder="email@exemplu.ro" inputmode="email" autocomplete="email">
                                    </div>
                                </div>
                                <label class="s-label" for="cart-address">Adresă (stradă, număr, bloc, apartament)</label>
                                <input type="text" class="s-input" id="cart-address" name="address" placeholder="Stradă, număr, bloc, apartament" autocomplete="street-address" required>
                                <div class="s-row">
                                    <div class="s-field">
                                        <label class="s-label" for="cart-city">Localitate</label>
                                        <input type="text" class="s-input" id="cart-city" name="city" placeholder="Oraș / localitate" autocomplete="address-level2" required>
                                    </div>
                                    <div class="s-field">
                                        <label class="s-label" for="cart-postal">Cod poștal</label>
                                        <input type="text" class="s-input" id="cart-postal" name="postal_code" placeholder="300000" inputmode="numeric" autocomplete="postal-code" required>
                                    </div>
                                </div>
                                <div class="s-row">
                                    <div class="s-field">
                                        <label class="s-label" for="cart-country">Țară</label>
                                        <select class="s-input" id="cart-country" name="country" autocomplete="country">
                                            <option value="RO">România</option>
                                            <option value="MD">Republica Moldova</option>
                                            <option value="DE">Germania</option>
                                            <option value="IT">Italia</option>
                                        </select>
                                    </div>
                                    <div class="s-field">
                                        <label class="s-label" for="cart-county">Județ</label>
                                        <select class="s-input" id="cart-county" name="county" autocomplete="address-level1">
                                            <option value="TM">Timiș</option>
                                            <option value="B">București</option>
                                            <option value="CJ">Cluj</option>
                                            <option value="IS">Iași</option>
                                            <option value="AB">Alba</option>
                                            <option value="AR">Arad</option>
                                            <option value="AG">Argeș</option>
                                            <option value="BC">Bacău</option>
                                            <option value="BH">Bihor</option>
                                            <option value="BN">Bistrița-Năsăud</option>
                                            <option value="BT">Botoșani</option>
                                            <option value="BR">Brăila</option>
                                            <option value="BV">Brașov</option>
                                            <option value="BZ">Buzău</option>
                                            <option value="CL">Călărași</option>
                                            <option value="CS">Caraș-Severin</option>
                                            <option value="CT">Constanța</option>
                                            <option value="CV">Covasna</option>
                                            <option value="DB">Dâmbovița</option>
                                            <option value="DJ">Dolj</option>
                                            <option value="GL">Galați</option>
                                            <option value="GR">Giurgiu</option>
                                            <option value="GJ">Gorj</option>
                                            <option value="HR">Harghita</option>
                                            <option value="HD">Hunedoara</option>
                                            <option value="IL">Ialomița</option>
                                            <option value="IF">Ilfov</option>
                                            <option value="MM">Maramureș</option>
                                            <option value="MH">Mehedinți</option>
                                            <option value="MS">Mureș</option>
                                            <option value="NT">Neamț</option>
                                            <option value="OT">Olt</option>
                                            <option value="PH">Prahova</option>
                                            <option value="SM">Satu Mare</option>
                                            <option value="SJ">Sălaj</option>
                                            <option value="SB">Sibiu</option>
                                            <option value="SV">Suceava</option>
                                            <option value="TR">Teleorman</option>
                                            <option value="TL">Tulcea</option>
                                            <option value="VL">Vâlcea</option>
                                            <option value="VS">Vaslui</option>
                                            <option value="VN">Vrancea</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn-update-total">Actualizează totalul</button>
                            </form>

                            <button type="button" class="btn-checkout" data-cart-submit-order>
                                FINALIZEAZĂ COMANDA <i class="fa-solid fa-arrow-right"></i>
                            </button>

                            <div class="trust-badges">
                                <span class="trust-badge"><i class="fa-solid fa-lock"></i> Plată securizată</span>
                                <span class="trust-badge"><i class="fa-solid fa-rotate-left"></i> Retur 14 zile</span>
                                <span class="trust-badge"><i class="fa-solid fa-shield-halved"></i> Garanție</span>
                            </div>
                        </div>
                    </div>
                </aside>

            </div>
        </div>
    </section>
</main>


<?php include_once 'system/footer.php'; ?>

</div>

<?php besoiu_render_scripts('cart'); ?>
</body>
</html>
