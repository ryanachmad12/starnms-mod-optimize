$ErrorActionPreference = 'SilentlyContinue'
$redisCli = Get-Command redis-cli.exe -ErrorAction SilentlyContinue
if ($redisCli) {
    $pong = & $redisCli.Source ping 2>$null
    if ($pong -eq 'PONG') { Write-Host 'STARNMS: Redis already active.'; exit 0 }
}
$server = @('C:\Program Files\Redis\redis-server.exe','C:\Redis\redis-server.exe','C:\xampp\redis\redis-server.exe') | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if ($server) {
    Start-Process -WindowStyle Hidden -FilePath $server
    Start-Sleep -Seconds 1
    Write-Host "STARNMS: Redis started from $server"
} else {
    $docker = Get-Command docker.exe -ErrorAction SilentlyContinue
    if ($docker) {
        $running = & $docker.Source ps --filter 'name=starnms-redis' --format '{{.Names}}' 2>$null
        if (-not $running) {
            & $docker.Source ps -a --filter 'name=starnms-redis' --format '{{.Names}}' 2>$null | ForEach-Object { & $docker.Source start starnms-redis | Out-Null }
        }
        $running = & $docker.Source ps --filter 'name=starnms-redis' --format '{{.Names}}' 2>$null
        if (-not $running) {
            & $docker.Source run -d --name starnms-redis -p 6379:6379 redis:7-alpine | Out-Null
        }
        Write-Host 'STARNMS: Redis started in Docker.'
    } else {
        Write-Host 'STARNMS: Redis executable not found. Install Memurai or Docker Desktop.'
        exit 1
    }
}
