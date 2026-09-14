<?php

namespace App\Providers;

use App\Extraction\AgentExtractor;
use App\Extraction\FixtureExtractor;
use App\Extraction\YardObservationAgent;
use App\Scoping\VisionExtractor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AgentExtractor::class, fn (): AgentExtractor => new AgentExtractor(
            new YardObservationAgent,
            config()->string('yardscope.extraction.provider'),
            config()->string('yardscope.extraction.model'),
        ));

        $this->app->bind(VisionExtractor::class, fn (): VisionExtractor => config()->boolean('yardscope.extraction.live')
            ? $this->app->make(AgentExtractor::class)
            : new FixtureExtractor(self::fixturesDirectory()));
    }

    /**
     * The fixtures directory from config, relative to the project unless given as an absolute path.
     */
    public static function fixturesDirectory(): string
    {
        $configured = config()->string('yardscope.extraction.fixtures');

        return str_starts_with($configured, '/') ? $configured : base_path($configured);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
