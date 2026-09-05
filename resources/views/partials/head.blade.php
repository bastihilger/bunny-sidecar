@php($sidecar = app(\BunnySidecar\BunnySidecar\BunnySidecar::class))
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ filled($title ?? null) ? $title.' - '.$sidecar->name() : $sidecar->name() }}</title>

<link rel="stylesheet" href="{{ $sidecar->stylesheetUrl() }}">
@fluxAppearance
