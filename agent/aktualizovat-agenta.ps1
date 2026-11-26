# Aktualizace farm agenta na pocitaci farmy z USB disku. Spustit jako spravce:
#   powershell -ExecutionPolicy Bypass -File E:\farm-agent-0.2.16\aktualizovat-agenta.ps1
# (pismeno disku podle toho, jak se USB disk ukaze). Nastaveni config.yaml, venv a logy zustanou.
# Texty jsou bez diakritiky schvalne (Windows PowerShell 5.1 cte soubory bez BOM v ANSI).

$ErrorActionPreference = 'Stop'
$src = Split-Path -Parent $MyInvocation.MyCommand.Path
$task = 'matplace-farm-agent'

$admin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $admin) { Write-Host 'Spustte PowerShell jako spravce.' -ForegroundColor Red; exit 1 }

# 1) kde agent je: slozka z ulohy v Planovaci, jinak C:\farm-agent
$dst = $null
$t = Get-ScheduledTask -TaskName $task -ErrorAction SilentlyContinue
if ($t -and $t.Actions[0].WorkingDirectory) { $dst = $t.Actions[0].WorkingDirectory.Trim('"') }
if (-not $dst) { $dst = 'C:\farm-agent' }
if (-not (Test-Path "$dst\config.yaml")) {
    Write-Host "V $dst neni config.yaml - agent tu neni nainstalovany. Nic nemenim." -ForegroundColor Red; exit 1
}
$old = (Select-String -Path "$dst\farm_agent\__init__.py" -Pattern '__version__\s*=\s*"([^"]+)"' -ErrorAction SilentlyContinue).Matches.Groups[1].Value
$new = (Select-String -Path "$src\farm_agent\__init__.py" -Pattern '__version__\s*=\s*"([^"]+)"').Matches.Groups[1].Value
Write-Host "Agent ve slozce $dst : verze $old -> $new"

# 2) zaloha stare verze
$backup = "$dst\zaloha-$old"
Write-Host '1/5 Zaloha stare verze...'
if (-not (Test-Path $backup)) {
    New-Item -ItemType Directory -Path $backup | Out-Null
    Copy-Item "$dst\farm_agent" "$backup\farm_agent" -Recurse
    Copy-Item "$dst\requirements.txt", "$dst\run-agent.cmd" $backup -ErrorAction SilentlyContinue
}

# 3) zastavit agenta
Write-Host '2/5 Zastavuji agenta...'
if ($t) { Stop-ScheduledTask -TaskName $task -ErrorAction SilentlyContinue }
Get-CimInstance Win32_Process -Filter "Name='python.exe'" | Where-Object { $_.CommandLine -like '*farm_agent*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
Start-Sleep -Seconds 3

# 4) nove soubory (config.yaml, venv, work a logy se nemeni)
Write-Host '3/5 Kopiruji novou verzi...'
Get-ChildItem "$dst\farm_agent" -Recurse -Directory -Filter '__pycache__' | Remove-Item -Recurse -Force
Copy-Item "$src\farm_agent" $dst -Recurse -Force
foreach ($f in 'requirements.txt', 'run-agent.cmd', 'install-farma.ps1', 'README.md', 'config.example.yaml') {
    if (Test-Path "$src\$f") { Copy-Item "$src\$f" $dst -Force }
}

# 5) knihovny (kdyby novejsi verze chtela dalsi)
Write-Host '4/5 Knihovny...'
$py = "$dst\venv\Scripts\python.exe"
if (Test-Path $py) {
    & $py -m pip install --quiet -r "$dst\requirements.txt" Pillow
    if ($LASTEXITCODE -ne 0) { Write-Host 'Pozor: instalace knihoven selhala (je internet?). Zkousim agenta i tak.' -ForegroundColor Yellow }
} else { $py = 'python' }

# 6) zkouska a spusteni
Write-Host '5/5 Zkouska agenta (--check) a spusteni:'
Push-Location $dst
& $py -m farm_agent --config "$dst\config.yaml" --check
$check = $LASTEXITCODE
Pop-Location
if ($check -ne 0) {
    Write-Host "Zkouska neprosla. Vracim starou verzi ze $backup ..." -ForegroundColor Red
    Copy-Item "$backup\farm_agent" $dst -Recurse -Force
    if ($t) { Start-ScheduledTask -TaskName $task }
    Write-Host 'Stara verze bezi dal. Poslete fotku teto obrazovky.' -ForegroundColor Red
    exit 1
}
if ($t) { Start-ScheduledTask -TaskName $task; Start-Sleep -Seconds 5; $state = (Get-ScheduledTask -TaskName $task).State }
else { Write-Host 'Uloha v Planovaci neexistuje - spustte install-farma.ps1.' -ForegroundColor Yellow; $state = '-' }
Write-Host "Hotovo. Agent $new bezi, uloha '$task' je ve stavu: $state" -ForegroundColor Green
Write-Host "Vypis agenta: $dst\agent.log"
