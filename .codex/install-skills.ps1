[CmdletBinding()]
param(
    [string[]]$SkillNames = @("*")
)

$sourceRoot = Join-Path $PSScriptRoot "skills"
$targetRoot = Join-Path $env:USERPROFILE ".codex\skills"

if (-not (Test-Path $sourceRoot)) {
    throw "Source skill directory not found: $sourceRoot"
}

New-Item -ItemType Directory -Force -Path $targetRoot | Out-Null

$availableSkills = Get-ChildItem -Path $sourceRoot -Directory | Sort-Object Name
if (-not $availableSkills) {
    throw "No skills found in $sourceRoot"
}

if ($SkillNames.Count -eq 1 -and $SkillNames[0] -eq "*") {
    $selectedSkills = $availableSkills
} else {
    $lookup = @{}
    foreach ($skill in $availableSkills) {
        $lookup[$skill.Name] = $skill
    }

    $selectedSkills = @()
    foreach ($name in $SkillNames) {
        if (-not $lookup.ContainsKey($name)) {
            $available = ($availableSkills.Name -join ", ")
            throw "Unknown skill '$name'. Available skills: $available"
        }

        $selectedSkills += $lookup[$name]
    }
}

foreach ($skill in $selectedSkills) {
    $targetPath = Join-Path $targetRoot $skill.Name

    if (Test-Path $targetPath) {
        Remove-Item -Path $targetPath -Recurse -Force
    }

    Copy-Item -Path $skill.FullName -Destination $targetRoot -Recurse -Force
    Write-Host "Installed skill: $($skill.Name) -> $targetPath"
}

Write-Host "Skill installation complete."
