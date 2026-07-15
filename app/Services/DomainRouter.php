<?php

namespace App\Services;

class DomainRouter
{
    private IndonesianTypoNormalizer $typos;

    public function __construct(
        private BusinessProfileResolver $businesses,
        private DomainGate $healthGate,
        ?IndonesianTypoNormalizer $typos = null,
    ) {
        $this->typos = $typos ?? new IndonesianTypoNormalizer;
    }

    public function local(string $message, array $state = []): ?string
    {
        $normalized = $this->typos->normalize($message);
        if ($this->isPromptInjection($normalized) || $this->healthGate->isClearlyOffTopic($message)) {
            return 'off_topic';
        }

        $companySignals = [
            'tentang walatra', 'walatra itu', 'profil perusahaan', 'perusahaan apa', 'sejarah perusahaan',
            'visi misi', 'alamat kantor', 'lokasi kantor', 'nomor telepon', 'nomor whatsapp', 'kontak',
            'jam operasional', 'jam buka', 'legalitas', 'reseller', 'cara pesan', 'cara membeli',
            'pengiriman', 'pembayaran', 'website', 'instagram',
        ];
        if ($this->containsAny($normalized, $companySignals) && $this->enabled('company_profile')) {
            return 'company_profile';
        }
        if ($this->healthGate->hasHealthSignal($message) && $this->enabled('health_herbal')) {
            return 'health_herbal';
        }
        if (! empty($state['active_domain']) && $this->enabled((string) $state['active_domain'])) {
            return (string) $state['active_domain'];
        }

        return null;
    }

    public function enabled(string $domain): bool
    {
        return in_array($domain, $this->businesses->enabledDomains(), true);
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isPromptInjection(string $text): bool
    {
        return $this->containsAny($text, ['abaikan aturan', 'ignore previous', 'system prompt', 'developer message']);
    }
}
