<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Mailing API</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        button { padding: 10px 20px; font-size: 16px; cursor: pointer; background: #000; color: #fff; border: none; border-radius: 5px; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; margin-top: 20px; border: 1px solid #ddd; }
    </style>
</head>
<body>

    <h2>Mailing Queue API Tester</h2>
    <p>Click the button below to send a mock order receipt to the queue.</p>
    
    <button onclick="testAPI()">Send Event to Queue</button>

    <h3>Response:</h3>
    <pre id="result">Waiting for test...</pre>

    <script>
        function testAPI() {
            document.getElementById('result').innerText = "Sending...";
            
            // Sending directly to the file in the same folder, avoiding URL redirect issues
            fetch('event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    "app_slug": "scrummy",
                    "event_type": "receipt",
                    "recipient_email": "test@example.com",
                    "payload": {
                        "customer_name": "O.F Oyewole",
                        "order_number": "ORD-BROWSER",
                        "total_amount": "₦ 12,500"
                    }
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('result').innerText = JSON.stringify(data, null, 4);
            })
            .catch(error => {
                document.getElementById('result').innerText = 'Network Error: ' + error;
            });
        }
    </script>

</body>
</html>