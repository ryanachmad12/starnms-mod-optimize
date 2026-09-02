param(
    [int]$Workers = 0,
    [string]$Php = 'C:\xampp\php\php.exe',
    [string]$App = 'C:\xampp\htdocs\STARNMS VERSION1'
)

if ($Workers -le 0) {
    # Leave one logical CPU for web/database/OS; never start fewer than 2.
    $Workers = [Math]::Max(2, [Environment]::ProcessorCount - 1)
}

Set-Location -LiteralPath $App
for ($i = 1; $i -le $Workers; $i++) {
    Start-Process -WindowStyle Hidden -FilePath $Php -ArgumentList @(
        'artisan', 'queue:work', 'redis', '--queue=monitoring', '--sleep=1',
        '--tries=2', '--timeout=25', '--max-time=3600', "--name=starnms-worker-$i"
    ) -WorkingDirectory $App
}
Write-Host "STARNMS: started $Workers Redis workers on $([Environment]::ProcessorCount) logical CPUs."
