<?php

arch('the scoping domain does not depend on Laravel')
    ->expect('App\Scoping')
    ->not->toUse(['Illuminate', 'Laravel\\Ai', 'config', 'env', 'app', 'now', 'collect']);

arch('scoping data objects are final and readonly')
    ->expect('App\Scoping\Data')
    ->toBeFinal()
    ->toBeReadonly();

arch('scoping enums are string backed')
    ->expect('App\Scoping\Enums')
    ->toBeStringBackedEnums();

arch('scoping exceptions are final')
    ->expect('App\Scoping\Exceptions')
    ->toBeFinal();

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();
