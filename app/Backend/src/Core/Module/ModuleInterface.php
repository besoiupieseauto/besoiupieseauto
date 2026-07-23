<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 * Contract pentru module plugabile (opționale).
 * CORE (Auth, Produse, Comenzi…) NU implementează asta — e „OS-ul”.
 * Un modul = mini-MVP propriu (src/, pages/, assets/, api/) conectat la CORE.
 */
interface ModuleInterface
{
    public function id(): string;

    public function name(): string;

    public function version(): string;

    /** core | optional */
    public function type(): string;

    public function manifest(): ModuleManifest;

    /**
     * Înregistrare la boot (rute metadata, CRUD keys, event listeners).
     * Nu trebuie să arunce dacă dependențe soft lipsesc — skip grațios.
     */
    public function boot(ModuleContext $context): void;

    /**
     * @return list<array{id: string, label: string, href: string, icon?: string, group?: string}>
     */
    public function navItems(): array;

    /**
     * @return list<string> chei RBAC (ex: website.blog)
     */
    public function permissions(): array;

    /**
     * @return list<string> id-uri module obligatorii (ex: produse)
     */
    public function dependencies(): array;

    /** Activate: migrări / seed (idempotent). */
    public function install(): void;

    /** Deactivate curat (nu șterge neapărat datele). */
    public function uninstall(): void;
}
