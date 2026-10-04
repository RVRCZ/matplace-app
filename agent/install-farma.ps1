# Instalace farm agenta na pocitaci farmy. Spustit jako spravce:
#   powershell -ExecutionPolicy Bypass -File C:\farm-agent\install-farma.ps1
# Texty jsou bez diakritiky schvalne (Windows PowerShell 5.1 cte soubory bez BOM v ANSI).

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$task = 'matplace-farm-agent'

$admin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $admin) { Write-Host 'Spustte PowerShell jako spravce.' -ForegroundColor Red; exit 1 }

if (-not (Test-Path "$root\config.yaml")) {
    Write-Host "Chybi $root\config.yaml (zkopirujte ho z ridiciho pocitace, je v nem token)." -ForegroundColor Red; exit 1
}

# 1) Python
$py = Get-Command py -ErrorAction SilentlyContinue
if ($py) { $python = @('py', '-3') } else {
    $p = Get-Command python -ErrorAction SilentlyContinue
    if (-not $p) { Write-Host 'Neni nainstalovany Python 3.11+ (python.org, zaskrtnout "Add to PATH").' -ForegroundColor Red; exit 1 }
    $python = @('python')
}

# 2) venv + knihovny
Write-Host '1/5 Python prostredi a knihovny...'
if (-not (Test-Path "$root\venv\Scripts\python.exe")) {
    if ($python.Count -gt 1) { & $python[0] $python[1] -m venv "$root\venv" } else { & $python[0] -m venv "$root\venv" }
}
& "$root\venv\Scripts\python.exe" -m pip install --quiet --upgrade pip
& "$root\venv\Scripts\python.exe" -m pip install --quiet -r "$root\requirements.txt" Pillow
if ($LASTEXITCODE -ne 0) { Write-Host 'Instalace knihoven selhala (je internet?).' -ForegroundColor Red; exit 1 }

# 3) pocitac nesmi usinat
Write-Host '2/5 Vypinam usinani a hibernaci...'
powercfg /change standby-timeout-ac 0
powercfg /change standby-timeout-dc 0
powercfg /change hibernate-timeout-ac 0
powercfg /change hibernate-timeout-dc 0

# 4) tiskarny v siti
Write-Host '3/5 Tiskarny z config.yaml:'
$urls = Select-String -Path "$root\config.yaml" -Pattern '^\s*url:\s*(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
foreach ($u in $urls) {
    $code = & curl.exe -s -m 6 -o NUL -w '%{http_code}' "$u/server/info"
    if ($code -eq '200') { Write-Host "   OK       $u" -ForegroundColor Green }
    else { Write-Host "   NEODPOVIDA $u  (vypnuta, jina adresa, nebo bez Rinkhals)" -ForegroundColor Yellow }
}

# 5) zkouska spojeni se serverem (nic nespousti)
Write-Host '4/5 Zkouska agenta (--check):'
Push-Location $root
& "$root\venv\Scripts\python.exe" -m farm_agent --config "$root\config.yaml" --check
$check = $LASTEXITCODE
Pop-Location
if ($check -ne 0) {
    Write-Host 'Zkouska neprosla - ulohu nezakladam. Opravte config.yaml / sit a spustte skript znovu.' -ForegroundColor Red
    exit 1
}

# 6) uloha v Planovaci: start s pocitacem, restart po padu
Write-Host '5/5 Uloha v Planovaci uloh...'
$action = New-ScheduledTaskAction -Execute "$root\run-agent.cmd" -WorkingDirectory $root
$trigger = New-ScheduledTaskTrigger -AtStartup
$trigger.Delay = 'PT30S'
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable `
    -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit (New-TimeSpan -Seconds 0) `
    -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName $task -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force | Out-Null
Start-ScheduledTask -TaskName $task
Start-Sleep -Seconds 5

$state = (Get-ScheduledTask -TaskName $task).State
Write-Host "Hotovo. Uloha '$task' je ve stavu: $state" -ForegroundColor Green
Write-Host "Vypis agenta: $root\agent.log"
Write-Host "Zastavit:  Stop-ScheduledTask -TaskName $task"
Write-Host "Spustit:   Start-ScheduledTask -TaskName $task"
