<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ccc; border-radius: 5px; }
        input, select, textarea, button { width: 100%; padding: 8px; margin-bottom: 10px; box-sizing: border-box; }
        button { background-color: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .result { margin-top: 20px; padding: 10px; border: 1px solid #eee; background-color: #f9f9f9; white-space: pre-wrap; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ $pageTitle }}</h1>
        <p>This form demonstrates the validation example from <code>ExampleController::validateExample()</code>.</p>

        <form id="validationForm" action="/validate-example-post" method="POST">
            <label for="username">Username (Required, Min 5):</label>
            <input type="text" id="username" name="username" value="testuser">

            <label for="password">Password (Required, Min 8):</label>
            <input type="password" id="password" name="password" value="password123">

            <label for="status">Status (Required, In: active, inactive, pending):</label>
            <select id="status" name="status">
                <option value="active">active</option>
                <option value="inactive">inactive</option>
                <option value="pending">pending</option>
                <option value="invalid">invalid</option>
            </select>

            <label for="contactEmail">Contact Email (Required, Email):</label>
            <input type="text" id="contactEmail" name="contact[email]" value="test@example.com">

            <label for="contactAddressCity">Contact Address City (Required):</label>
            <input type="text" id="contactAddressCity" name="contact[address][city]" value="Berlin">

            <label for="tags0Id">Tag 1 ID (Required):</label>
            <input type="text" id="tags0Id" name="tags[0][id]" value="101">

            <label for="tags0Name">Tag 1 Name (Required, Min 3):</label>
            <input type="text" id="tags0Name" name="tags[0][name]" value="PHP">

            <label for="tags1Id">Tag 2 ID (Optional):</label>
            <input type="text" id="tags1Id" name="tags[1][id]" value="102">

            <label for="tags1Name">Tag 2 Name (Optional):</label>
            <input type="text" id="tags1Name" name="tags[1][name]" value="Laravel">

            <label for="metadataJson">Metadata JSON (Valid JSON):</label>
            <textarea id="metadataJson" name="metadata_json" rows="4">{ "version": "1.0", "author": "Roo" }</textarea>

            <label for="invalidJson">Invalid JSON (Invalid JSON):</label>
            <textarea id="invalidJson" name="invalid_json" rows="4">{"key": "value"</textarea>

            <button type="submit">Validate Data</button>
        </form>

        <div class="result" id="validationResult">
            <!-- Validation results will be displayed here -->
        </div>
    </div>

    <script>
        document.getElementById('validationForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const data = {};
            for (let [key, value] of formData.entries()) {
                const parts = key.match(/([^\[\]]+)/g);
                let current = data;
                for (let i = 0; i < parts.length; i++) {
                    const part = parts[i];
                    if (i === parts.length - 1) {
                        current[part] = value;
                    } else {
                        if (!current[part]) {
                            current[part] = isNaN(Number(parts[i+1])) ? {} : [];
                        }
                        current = current[part];
                    }
                }
            }

            const tags = [];
            let tagIndex = 0;
            while (formData.has(`tags[${tagIndex}][id]`)) {
                tags.push({
                    id: formData.get(`tags[${tagIndex}][id]`),
                    name: formData.get(`tags[${tagIndex}][name]`)
                });
                tagIndex++;
            }
            if (tags.length > 0) {
                data.tags = tags;
            } else {
                delete data.tags;
            }

            try {
                const response = await fetch(form.action, {
                    method: form.method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                const resultDiv = document.getElementById('validationResult');
                resultDiv.innerHTML = `<h3>Validation Result:</h3><pre class="${result.status}">${JSON.stringify(result, null, 2)}</pre>`;
            } catch (error) {
                const resultDiv = document.getElementById('validationResult');
                resultDiv.innerHTML = `<h3 class="error">Error:</h3><pre>${error.message}</pre>`;
            }
        });
    </script>
</body>
</html>