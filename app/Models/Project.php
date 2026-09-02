<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
class Project extends Model {
    protected $fillable=['name','customer_name','contract_start_date','contract_end_date','location','maintenance_interval_months','maintenance_anchor_date','notes','is_active'];
    protected $casts=['contract_start_date'=>'date','contract_end_date'=>'date','maintenance_anchor_date'=>'date','is_active'=>'boolean'];
    public function getContractStatusAttribute(): string { if(!$this->is_active)return 'inactive'; if($this->contract_end_date->isPast())return 'expired'; if(now()->diffInDays($this->contract_end_date,false)<=30)return 'expiring'; return 'active'; }
    public function getNextMaintenanceDateAttribute(): Carbon { $next=$this->maintenance_anchor_date->copy(); while($next->isPast())$next->addMonthsNoOverflow($this->maintenance_interval_months); return $next; }
    public function devices(){return $this->hasMany(Device::class);}
}
