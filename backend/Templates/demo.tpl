{# Dies ist ein Kommentar in der neuen Template-Engine #}
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
        .highlight { color: green; font-weight: bold; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ $greeting }}, {{ $userName }}!</h1>

        <ul>
            {% foreach $features as $feature %}
                <li>{{ $feature['name'] }}: {{ $feature['description'] }}</li>
            {% endforeach %}
        </ul>

        <h2>Conditional Content</h2>
        {% if $isAdmin === true %}
            <p class="highlight">You are an administrator. Access granted to sensitive content.</p>
        {% else %}
            <p class="error">You are a regular user. Some content is restricted.</p>
        {% endif %}

        {% if $showExtraContent %}
            <p>This content is only shown if 'showExtraContent' is true.</p>
        {% endif %}

        <h2>Included Content</h2>
        {% include "partial_info.tpl" %}

        <h2>Raw Variable Example</h2>
        <p>Raw HTML (not escaped): {{ $rawHtml|raw }}</p>
    </div>
</body>
</html>
