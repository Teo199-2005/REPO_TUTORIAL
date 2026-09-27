<?php
namespace App\Models;

use CodeIgniter\Model;

class SystemSettingModel extends Model
{
    protected $table = 'system_settings';
    protected $primaryKey = 'id';
    protected $allowedFields = ['setting_key', 'setting_value', 'description'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getSetting($key, $default = null)
    {
        $setting = $this->where('setting_key', $key)->first();
        return $setting ? $setting['setting_value'] : $default;
    }

    public function setSetting($key, $value, $description = null)
    {
        $existing = $this->where('setting_key', $key)->first();
        
        if ($existing) {
            return $this->update($existing['id'], ['setting_value' => $value]);
        } else {
            return $this->insert([
                'setting_key' => $key,
                'setting_value' => $value,
                'description' => $description
            ]);
        }
    }

    /**
     * Remove a setting row entirely.
     *
     * The counterpart to setSetting(), for the "go back to the default" case.
     * Writing an empty string would leave a row that looks configured but means
     * nothing, which is exactly the kind of half-set state that is hard to
     * diagnose later.
     *
     * @return bool True when a row was removed, false when there was nothing to remove.
     */
    public function deleteSetting($key)
    {
        return (bool) $this->where('setting_key', $key)->delete();
    }

    public function getCurrentTerm()
    {
        return (int) $this->getSetting('current_term', 1);
    }

    public function setCurrentTerm($term)
    {
        return $this->setSetting('current_term', $term, 'Current active term for grading');
    }
}