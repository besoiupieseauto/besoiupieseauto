<?php
declare(strict_types=1);

namespace Evasystem\Core\Auth;

use Evasystem\Core\AdvancedCRUD;

final class RolesRepository
{
    /**
     * Returnează harta $ROLES în formatul așteptat de Permision:
     * [
     *   'executive' => ['label'=>'...', 'scopes'=>[], 'nav'=>['Label'=>'/path', ...], 'widgets'=>['kpi_x', ...]],
     *   ...
     * ]
     */

    public static function roles():array
    {
        return AdvancedCRUD::select('roles', '*', "WHERE is_active = 1 ORDER BY sort_order, id");
    }


    public static function loadAll(): array
    {
        $roles = [];

        // 1) roles
        $rows = AdvancedCRUD::select('roles', '*', "WHERE is_active = 1 ORDER BY sort_order, id");
        foreach ($rows as $r) {
            $slug   = (string)$r['slug'];
            $label  = (string)$r['label'];
            $scopes = self::jsonDecodeList($r['scopes'] ?? '[]');

            $roles[$slug] = [
                'label'   => $label,
                'scopes'  => $scopes,
                'nav'     => [],
                'widgets' => [],
            ];
        }

        if (empty($roles)) {
            // fallback minim ca să nu crape aplicația
            $roles['guest'] = ['label'=>'Vizitator','scopes'=>[],'nav'=>['Autentificare'=>'/public/login','Înregistrare'=>'/public/reg'],'widgets'=>['howto']];
            return $roles;
        }

        // 2) nav
        // ia doar coloanele necesare și ordonează ierarhic
        $navRows = AdvancedCRUD::select(
            'role_nav',
            'role_slug,label,url,parent_id,sort_order,id,is_active',
            'WHERE is_active = 1 ORDER BY role_slug, COALESCE(parent_id,0), sort_order, id'
        );

        foreach ($navRows as $n) {
            $slug = (string)($n['role_slug'] ?? '');
            if ($slug === '' || !isset($roles[$slug])) continue;

            $label = (string)($n['label'] ?? '');
            $url   = $n['url'] ?? null;        // ← nu mai folosi $n['path']

            // dacă e părinte (grup), url este NULL => nu-l pune în mapul flat
            if ($url === null || $url === '') continue;

            // construiește mapul simplu label => url
            $roles[$slug]['nav'][$label] = $url;
        }

        // 3) widgets
        $wRows = AdvancedCRUD::select('role_widgets', '*', "WHERE is_active = 1 ORDER BY role_slug, sort_order, id");
        foreach ($wRows as $w) {
            $slug = (string)$w['role_slug'];
            if (!isset($roles[$slug])) continue;
            $roles[$slug]['widgets'][] = (string)$w['widget_key'];
        }

        return $roles;
    }

    /** decodează JSON într-o listă; dacă e invalid -> [] */
    private static function jsonDecodeList($json): array
    {
        if (is_array($json)) return $json;
        if (!is_string($json) || $json === '') return [];
        $arr = json_decode($json, true);
        return is_array($arr) ? $arr : [];
    }
}
