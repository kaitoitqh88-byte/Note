# Update all fonts to Roboto across the project
Get-ChildItem -Recurse -Include "*.php","*.html","*.css","*.js" | ForEach-Object {
    $content = Get-Content $_.FullName -Raw
    $content = $content -replace "font-family: 'Orbitron', monospace;", "font-family: 'Roboto', sans-serif;"
    $content = $content -replace "font-family: 'Courier New', monospace;", "font-family: 'Roboto Mono', monospace;"
    $content = $content -replace "font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;", "font-family: 'Roboto', sans-serif;"
    $content = $content -replace "font-family: Arial, sans-serif;", "font-family: 'Roboto', sans-serif;"
    $content | Set-Content $_.FullName -NoNewline
    Write-Host "Updated: $($_.FullName)"
}