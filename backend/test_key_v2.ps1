$apiKey = "AIzaSyAhnOTpB_YmYYGPpTp5b789DYF-zYv3Cos"
$results = @()

function Test-Model ($model, $version) {
    $url = "https://generativelanguage.googleapis.com/$version/models/$($model):generateContent?key=$apiKey"
    $body = '{ "contents": [{ "parts": [{ "text": "Test" }] }] }'
    
    Write-Host "Testing $model ($version)..." -NoNewline
    try {
        $response = Invoke-RestMethod -Uri $url -Method Post -Body $body -ContentType "application/json" -ErrorAction Stop
        Write-Host " SUCCESS! ✅" -ForegroundColor Green
        return "SUCCESS"
    } catch {
        $err = $_.Exception.Response
        $status = "UNKNOWN"
        $msg = $_.Exception.Message
        if ($err) {
            $status = $err.StatusCode
            $stream = $err.GetResponseStream()
            if ($stream) {
                $reader = New-Object System.IO.StreamReader($stream)
                $msg = $reader.ReadToEnd()
            }
        }
        Write-Host " FAILED ($status) ❌" -ForegroundColor Red
        return "$status - $msg"
    }
}

$results += "gemini-2.0-flash (v1beta): " + (Test-Model "gemini-2.0-flash" "v1beta")
$results += "gemini-1.5-flash (v1beta): " + (Test-Model "gemini-1.5-flash" "v1beta")
$results += "gemini-1.5-flash (v1): " + (Test-Model "gemini-1.5-flash" "v1")
$results += "gemini-pro (v1beta): " + (Test-Model "gemini-pro" "v1beta")

$results | Out-File "c:\Users\Moises James Q. Sy\Documents\GitHub\MJM_Project\backend\api_test_results.txt"
