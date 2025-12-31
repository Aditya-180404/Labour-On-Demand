$iniPath = "C:\xampp\php\php.ini"
$backupPath = "C:\xampp\php\php.ini.bak"

Write-Host "Configuring PHP OPcache..."

if (!(Test-Path $iniPath)) {
    Write-Error "ERROR: php.ini not found at $iniPath. Please edit key values manually."
    exit 1
}

# 1. Backup
Copy-Item $iniPath $backupPath -Force
Write-Host "SUCCESS: Backed up php.ini to $backupPath"

# 2. Read Content
$content = Get-Content $iniPath -Raw

# 3. Enable Extension
# XAMPP often has ';zend_extension=opcache' commented out
if ($content -match ";zend_extension=opcache") {
    $content = $content -replace ";zend_extension=opcache", "zend_extension=opcache"
    Write-Host "SUCCESS: Uncommented 'zend_extension=opcache'"
} elseif (!($content -match "zend_extension=opcache")) {
    # If not found, verify dll exists and add it
    if (Test-Path "C:\xampp\php\ext\php_opcache.dll") {
        $content += "`r`nzend_extension=php_opcache.dll"
        Write-Host "SUCCESS: Added 'zend_extension=php_opcache.dll'"
    } else {
        $content += "`r`nzend_extension=opcache"
        Write-Host "SUCCESS: Added 'zend_extension=opcache' (generic)"
    }
} else {
    Write-Host "INFO: OPcache extension already enabled in config."
}

# 4. Add/Update Settings
# We use regex to check if settings exist, if so replace, if not append.
# For simplicity in this script, we'll append a dedicated block at the end if the key values aren't found, 
# or warn if they exist.

$userSettings = @{
    "opcache.enable" = "1"
    "opcache.memory_consumption" = "256"
    "opcache.max_accelerated_files" = "20000"
    "opcache.validate_timestamps" = "1"
    "opcache.revalidate_freq" = "2"
}

$configBlock = "`r`n`r`n[opcache_custom_settings]`r`n"
$needsUpdate = $false

foreach ($key in $userSettings.Keys) {
    if (!($content -match "$key\s*=")) {
        $configBlock += "$key=" + $userSettings[$key] + "`r`n"
        $needsUpdate = $true
    } else {
        # Rudimentary check - ideally we'd replace, but appending usually overrides in PHP parsing or creates duplicate keys
        # We will inform user
        Write-Host "INFO: Setting '$key' already exists in php.ini. Ensuring it effectively matches..."
        # Regex replace to ensure value
        $content = $content -replace "$key\s*=.*", "$key=$($userSettings[$key])"
        $needsUpdate = $true
    }
}

if ($needsUpdate) {
    if(!($content -match "\[opcache_custom_settings\]")) {
         $content += $configBlock
    }
    Set-Content $iniPath $content
    Write-Host "SUCCESS: Updated OPcache settings."
} else {
    Write-Host "INFO: Settings already appear correct."
}

Write-Host "`r`n----------------------------------------------------------------"
Write-Host "IMPORTANT: You MUST restart Apache for these changes to take effect."
Write-Host "1. Open XAMPP Control Panel"
Write-Host "2. Click 'Stop' next to Apache"
Write-Host "3. Click 'Start' next to Apache"
Write-Host "----------------------------------------------------------------"
