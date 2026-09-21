<?php

namespace App\Support;

use App\Models\Puskesmas;
use App\Models\User;
use Illuminate\Support\Str;

final class OfficerEmail
{
    public static function forPuskesmas(Puskesmas $puskesmas, ?User $except = null): string
    {
        $area = preg_replace('/^Puskesmas\s+/i', '', $puskesmas->nama_puskesmas);
        $slug = Str::slug($area ?: $puskesmas->nama_puskesmas, '_');
        $base = 'petugas_'.($slug ?: $puskesmas->puskesmas_id);
        $email = $base.'@test';
        $number = 2;

        while (User::query()
            ->where('email', $email)
            ->when($except, fn ($query) => $query->where($except->getKeyName(), '!=', $except->getKey()))
            ->exists()) {
            $email = $base.'_'.$number.'@test';
            $number++;
        }

        return $email;
    }
}
