<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Karyawan extends Model
{
    use HasFactory;

    protected $fillable = [
        'nik',
        'nama',
        'branch_id',
        'limit_potongan_harian',
        'jam_mulai',
        'jam_selesai',
        'is_active',
    ];

    protected $casts = [
        'jam_mulai' => 'datetime:H:i',
        'jam_selesai' => 'datetime:H:i',
        'is_active' => 'boolean',
        'limit_potongan_harian' => 'integer',
    ];

    public function getJamMulaiAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }

    public function getJamSelesaiAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }

    public function isDalamJamKerja(): bool
    {
        $now = now()->format('H:i');
        $mulai = $this->attributes['jam_mulai'];
        $selesai = $this->attributes['jam_selesai'];

        if (! $mulai || ! $selesai) {
            return false;
        }

        if ($mulai < $selesai) {
            return $now >= $mulai && $now <= $selesai;
        }

        return $now >= $mulai || $now <= $selesai;
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function getUsagePotonganHariIni(): int
    {
        return $this->transactions()
            ->whereDate('created_at', today())
            ->sum('potongan_makan');
    }

    public function getSisaPotongan(): int
    {
        $usage = $this->getUsagePotonganHariIni();

        return max(0, $this->limit_potongan_harian - $usage);
    }

    public function canGetPotongan(): bool
    {
        return $this->is_active && $this->isDalamJamKerja() && $this->getSisaPotongan() > 0;
    }
}
