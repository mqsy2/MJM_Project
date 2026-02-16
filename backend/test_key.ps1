$apiKey = "AIzaSyAhnOTpB_YmYYGPpTp5b789DYF-zYv3Cos"
$models = @("gemini-2.0-flash", "gemini-1.5-flash", "gemini-1.5-pro", "gemini-pro")

foreach ($model in $models) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$($model):generateContent?key=$apiKey"
    $body = '{ "contents": [{ "parts": [{ "text": "Test" }] }] }'
    
    Write-Host "Testing model: $model" -NoNewline
    
    try {
        $response = Invoke-RestMethod -Uri $url -Method Post -Body $body -ContentType "application/json" -ErrorAction Stop
        Write-Host " -> SUCCESS! ✅" -ForegroundColor Green
        # Write-Host $response
        exit
    } catch {
        $err = $_.Exception.Response
        if ($err) {
            Write-Host " -> FAILED ($($err.StatusCode)) ❌" -ForegroundColor Red
            # Print body if needed
            # $reader = New-Object System.IO.StreamReader($err.GetResponseStream())
            # Write-Host $reader.ReadToEnd()
        } else {
            Write-Host " -> ERROR: $($_.Exception.Message)"
        }
    }
}
