$apiKey = "AIzaSyAhnOTpB_YmYYGPpTp5b789DYF-zYv3Cos"

function Test-Model ($model) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$($model):generateContent?key=$apiKey"
    $body = '{ "contents": [{ "parts": [{ "text": "Test" }] }] }'
    
    Write-Host "Testing $model..." -NoNewline
    try {
        $response = Invoke-RestMethod -Uri $url -Method Post -Body $body -ContentType "application/json" -ErrorAction Stop
        Write-Host " SUCCESS! ✅" -ForegroundColor Green
    } catch {
        $err = $_.Exception.Response
        $status = "UNKNOWN"
        if ($err) { $status = $err.StatusCode }
        Write-Host " FAILED ($status) ❌" -ForegroundColor Red
    }
}

Test-Model "gemini-1.5-flash-001"
Test-Model "gemini-1.5-flash-002"
Test-Model "gemini-1.5-flash-8b"
Test-Model "gemini-1.0-pro"
