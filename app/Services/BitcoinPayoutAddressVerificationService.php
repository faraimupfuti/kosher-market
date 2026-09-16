<?php

namespace App\Services;

use App\Models\BitcoinPayoutAddressVerification;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BitcoinPayoutAddressVerificationService
{
    public function issue(Vendor $vendor, string $address): string
    {
        return DB::transaction(function () use ($vendor, $address) {
            BitcoinPayoutAddressVerification::where('vendor_id', $vendor->id)
                ->whereNull('verified_at')
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => now()]);

            $token = Str::random(64);

            BitcoinPayoutAddressVerification::create([
                'vendor_id' => $vendor->id,
                'address' => $address,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(30),
            ]);

            return $token;
        });
    }

    public function confirm(Vendor $vendor, string $token): void
    {
        DB::transaction(function () use ($vendor, $token) {
            $verification = BitcoinPayoutAddressVerification::where('vendor_id', $vendor->id)
                ->where('token_hash', hash('sha256', $token))
                ->whereNull('verified_at')
                ->whereNull('invalidated_at')
                ->lockForUpdate()
                ->first();

            if (! $verification || $verification->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'token' => 'The payout-address verification code is invalid or expired.',
                ]);
            }

            if ($vendor->bitcoin_payout_address !== $verification->address) {
                $verification->invalidated_at = now();
                $verification->save();

                throw ValidationException::withMessages([
                    'address' => 'The payout address changed. Please start verification again.',
                ]);
            }

            $verification->verified_at = now();
            $verification->save();

            $vendor->bitcoin_payout_address_verified_at = now();
            $vendor->save();
        });
    }
}
