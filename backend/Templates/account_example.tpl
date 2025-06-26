<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #0056b3; }
        ul { list-style-type: none; padding: 0; }
        li { background-color: #e9e9e9; margin-bottom: 5px; padding: 10px; border-radius: 4px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ $pageTitle }}</h1>
        <b>Warning: This example can be slow because we have a lot queries here. </b>
        <h2>Account Operations Log</h2>
        <ul>
            {% foreach $messages as $message %}
                <li>{{ $message }}</li>
            {% endforeach %}
        </ul>

        <h2>Current Status</h2>
        {% if $isLoggedIn %}
            <p class="success">You are logged in!</p>
            {% if $currentUser %}
                <p class="info">Logged in as: {{ $currentUser['email'] }}</p>
                <p class="info">First Name: {{ $currentUser['first_name'] ?? 'N/A' }}</p>
                <p class="info">Last Name: {{ $currentUser['last_name'] ?? 'N/A' }}</p>
            {% endif %}
        {% else %}
            <p class="error">You are not logged in.</p>
        {% endif %}
    </div>
</body>
</html>