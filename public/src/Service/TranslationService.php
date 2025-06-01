<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class TranslationService
{
    public function __construct(
        private ParameterBagInterface $parameterBag
    ) {
    }

    public function getTranslations(string $locale): array
    {
        // Lokalizacja plików tłumaczeń Symfony (zazwyczaj w katalogu translations)
        $translationsPath = $this->parameterBag->get('kernel.project_dir') . '/translations';

        // Wczytaj wszystkie pliki YAML dla danego języka
        $finder = new Finder();
        $finder->files()->in($translationsPath)->name("*.$locale.yaml")->name("*.$locale.yml");
        
        $translations = [];
        
        foreach ($finder as $file) {
            $domain = str_replace([".$locale.yaml", ".$locale.yml"], '', $file->getFilename());
            $ymlContent = Yaml::parseFile($file->getRealPath());
            $this->flattenYamlTranslations($ymlContent, $domain, $translations);
        }
        
        return $translations;
    }


        /**
     * Funkcja pomocnicza do przekształcania zagnieżdżonej struktury YAML na płaskie klucze
     */
    private function flattenYamlTranslations(array $yaml, string $domain, array &$result, string $prefix = '')
    {
        foreach ($yaml as $key => $value) {
            $fullKey = $prefix ? "$prefix.$key" : $key;
            
            if (is_array($value)) {
                $this->flattenYamlTranslations($value, $domain, $result, $fullKey);
            } else {
                if (!isset($result[$domain])) {
                    $result[$domain] = [];
                }
                $result[$domain][$fullKey] = $value;
            }
        }
    }
}