<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Setting;

class SettingController extends Controller
{
    /** Editable numeric settings and their validation type. */
    private const EDITABLE = [
        'lkr_rate'                    => 'decimal',
        'per_kilo_clearance'          => 'decimal',
        'handling_charge'             => 'decimal',
        'cost_rounding_step'          => 'int',
        'low_stock_threshold'         => 'int',
        'retention_softdelete_days'   => 'int',
        'retention_tmp_hours'         => 'int',
        'retention_activitylog_days'  => 'int',
        'retention_backups_keep'      => 'int',
    ];

    public function index(Request $request): void
    {
        $settings = new Setting();
        $this->view('settings/index', [
            'title'   => 'Settings',
            'grouped' => $settings->grouped(),
            'discountRules' => $this->db()->all(
                'SELECT d.*, b.name AS brand_name
                   FROM discount_rules d
              LEFT JOIN brands b ON b.id = d.brand_id
                  WHERE d.is_active = 1
               ORDER BY d.type, b.name'
            ),
        ]);
    }

    public function update(Request $request): void
    {
        $settings = new Setting();
        $saved = 0;

        foreach (self::EDITABLE as $key => $type) {
            $value = $request->input($key);
            if ($value === null || $value === '') {
                continue;
            }
            if ($type === 'int' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                continue;
            }
            if ($type === 'decimal' && !is_numeric($value)) {
                continue;
            }
            $settings->set($key, $value, Auth::id());
            $saved++;
        }

        $this->log('settings_updated', 'settings', null, ['count' => $saved]);
        Session::flash('success', "Settings saved ({$saved} updated).");
        $this->redirect('settings');
    }

    private function db(): \App\Core\Database
    {
        return \App\Core\Database::instance();
    }
}
