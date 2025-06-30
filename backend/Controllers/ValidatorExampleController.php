<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\Request;
use SquareRouting\Core\Response;
use SquareRouting\Core\View;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Validation\Rules\Email;
use SquareRouting\Core\Validation\Rules\In;
use SquareRouting\Core\Validation\Rules\IsArray;
use SquareRouting\Core\Validation\Rules\Json;
use SquareRouting\Core\Validation\Rules\Min;
use SquareRouting\Core\Validation\Rules\Required;
use SquareRouting\Core\Validation\Validator;

class ValidatorExampleController
{
    public Request $request;
    public View $view;

    public function __construct(DependencyContainer $container)
    {
        $this->request = $container->get(Request::class);
        $this->view = $container->get(View::class);
    }

    public function showValidatorExample(): Response
    {
        $data = [
            'pageTitle' => 'Validator Example Form',
        ];
        $this->view->setMultiple($data);
        $output = $this->view->render('validator_example.tpl');

        return (new Response)->html($output);
    }

    public function validateExample(): Response
    {
        $data = $this->request->json();

        // 2. The rules for the data
        $rules = [
            'username' => [new Required, new Min(5)],
            'password' => [new Required, new Min(8)],
            'status' => [new Required, new In(['active', 'inactive', 'pending'])],

            // Nested validation using dot notation
            'contact.email' => [new Required, new Email],
            'contact.address.city' => [new Required],

            // Array validation using the '*' wildcard
            'tags' => [new IsArray, new Min(1)], // The 'tags' field itself must be an array with at least 1 item.
            'tags.*.id' => [new Required], // Rule for each item in the 'tags' array
            'tags.*.name' => [new Required, new Min(3)],

            // JSON validation
            'metadata_json' => [new Json],
            'invalid_json' => [new Json],
        ];

        // 3. Create a validator instance and run it
        $validator = new Validator($data, $rules);

        if ($validator->fails()) {
            return (new Response)->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'data_used_for_validation' => $data,
            ], 400);
        } else {
            return (new Response)->json([
                'status' => 'success',
                'message' => 'Validation passed',
                'validated_data' => $validator->validated(),
                'data_used_for_validation' => $data,
            ], 200);
        }
    }
}