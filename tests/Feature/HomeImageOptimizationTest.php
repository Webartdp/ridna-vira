<?php

namespace Tests\Feature;

use App\Support\HomeImages;
use Tests\TestCase;

class HomeImageOptimizationTest extends TestCase
{
    public function test_legacy_images_gain_responsive_sources_and_lower_images_are_lazy(): void
    {
        $html = '<head></head><section class="hero-home"><img src="/assets/figma/home/ornament.png"></section><img src="/assets/figma/home/holiday-veles.png"><img src="/assets/figma/home/logo.png">';
        $html = HomeImages::optimize($html, true);
        $this->assertStringContainsString('holiday-veles-160.webp', $html);
        $this->assertStringContainsString('sizes="128px"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('rel="preload"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('width="1254" height="1254"', $html);
    }

    public function test_custom_hero_and_unrelated_images_are_preserved(): void
    {
        $external = '<img src="https://example.com/assets/figma/home/logo.png">';
        $this->assertSame($external, HomeImages::optimize($external));
        $youtube = '<img src="https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg">';
        $this->assertSame($youtube, HomeImages::optimize($youtube));
        $custom = HomeImages::optimize('<head></head><section class="hero-home" style="background-image:url(&quot;/storage/custom.webp&quot;)"></section>', true);
        $this->assertStringContainsString('/storage/custom.webp', $custom);
        $this->assertStringNotContainsString('/assets/figma/home/hero.png', $custom);
    }

    public function test_generated_manifest_points_to_real_files(): void
    {
        foreach (HomeImages::manifest() as $entry) {
            foreach ($entry['variants'] as $variant) {
                $this->assertFileExists(public_path($variant['src']));
            }
        }
    }
}
