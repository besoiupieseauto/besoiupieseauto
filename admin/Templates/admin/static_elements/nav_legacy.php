<?php

use Besoiu\Core\Module\ModuleGate;

?>
        <li class="side-menu__group-label">
            ADMIN PANEL
        </li>

        <li data-besoiu-section="dashboard">
            <a href="/admin/dashboard" class="side-menu__link" data-besoiu-global-nav="1">
                <i data-lucide="layout-dashboard" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Dashboard</div>
            </a>
        </li>

        <?php if (ModuleGate::slugAllowed('furnizori')): ?>
        <li data-besoiu-section="furnizori">
            <a href="/admin/suppliers" class="side-menu__link">
                <i data-lucide="truck" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Furnizori</div>
            </a>
        </li>
        <?php endif; ?>

        <?php if (ModuleGate::slugAllowed('clienti')): ?>
        <li data-besoiu-section="clienti">
            <a href="/admin/clienti" class="side-menu__link">
                <i data-lucide="users" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Clienți</div>
            </a>
        </li>
        <?php endif; ?>

        <?php if (ModuleGate::slugAllowed('supplier-search')): ?>
        <li data-besoiu-section="supplier-search">
            <a href="/admin/supplier-search" class="side-menu__link">
                <i data-lucide="search" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Căutare furnizori</div>
            </a>
        </li>
        <?php endif; ?>

        <?php if (ModuleGate::slugAllowed('scraper')): ?>
        <li data-besoiu-section="scraper">
            <a href="/admin/scraper" class="side-menu__link">
                <i data-lucide="image" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Scraper imagini</div>
            </a>
        </li>
        <?php endif; ?>

        <?php if (ModuleGate::slugAllowed('scraper-web')): ?>
        <li data-besoiu-section="scraper-web">
            <a href="/admin/scraper-web" class="side-menu__link">
                <i data-lucide="globe" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Scraper web</div>
            </a>
        </li>
        <?php endif; ?>

        <?php if (ModuleGate::slugAllowed('users')): ?>
        <li data-besoiu-section="users">
            <a href="/admin/users" class="side-menu__link">
                <i data-lucide="user-cog" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Utilizatori</div>
            </a>
        </li>
        <?php endif; ?>

        <li data-besoiu-section="settings">
            <a href="/admin/settings" class="side-menu__link">
                <i data-lucide="settings" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Setări</div>
            </a>
        </li>

        <li class="side-menu__group-label mt-4">
            MODULE
        </li>
        <li>
            <p class="px-3 py-2 text-xs opacity-60">Activează funcții suplimentare din Setări → Module.</p>
        </li>
