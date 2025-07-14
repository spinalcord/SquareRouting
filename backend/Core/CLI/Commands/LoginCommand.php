<?php

namespace SquareRouting\Core\CLI\Commands;
use  SquareRouting\Core\CLI\CommandRegistry;
use  SquareRouting\Core\CLI\StepFeedback;
use  SquareRouting\Core\CLI\CommandWizard;

class LoginCommand extends BaseCommand
{
    public function getName(): string
    {
        return 'login';
    }

    public function getDescription(): string
    {
        return 'Login to your account';
    }

    public function execute(array $args, string $input, string $commandId): array
    {
        if ($this->account->isLoggedIn()) {
            return $this->createResponse('you are already logged in.');
        }

        $wizard = new CommandWizard('user_login');
        $wizard
            ->addStep(
                'Enter your email:',
                function ($input, $data) {
                    $stepValidation = new \SquareRouting\Core\Validation\Validator(
                        ['email' => $input], 
                        ['email' => [new \SquareRouting\Core\Validation\Rules\Email]]
                    );
                    if ($stepValidation->fails()) {
                        $errors = [];
                        foreach ($stepValidation->errors() as $fieldErrors) {
                            $errors = array_merge($errors, $fieldErrors);
                        }
                        return (new StepFeedback)->warning(implode("\n", $errors));
                    }
                    return true;
                },
                function ($input, $data) {
                    return ['email' => trim($input)];
                }
            )
            ->addStep(
                'Enter your password:',
                function ($input, $data) {
                    $stepValidation = new \SquareRouting\Core\Validation\Validator(
                        ['password' => $input], 
                        ['password' => [
                            new \SquareRouting\Core\Validation\Rules\Required, 
                            new \SquareRouting\Core\Validation\Rules\IsString, 
                            new \SquareRouting\Core\Validation\Rules\Min(1)
                        ]]
                    );
                    if ($stepValidation->fails()) {
                        $errors = [];
                        foreach ($stepValidation->errors() as $fieldErrors) {
                            $errors = array_merge($errors, $fieldErrors);
                        }
                        return (new StepFeedback)->warning(implode("\n", $errors));
                    }
                    return true;
                },
                function ($input, $data) {
                    return ['password' => trim($input)];
                }
            )
            ->addQueueStep(
                function ($data) {
                    return 'Logging in...';
                },
                function ($input, $data) {
                    try {
                        $this->account->login($data['email'], $data['password']);
                        return (new StepFeedback)->success("Login successful! Welcome back ". $data['username'], true, additionalData: ["label" => $this->account->getCurrentUsername()]);
                    } catch (\InvalidArgumentException $e) {
                        return ['terminate' => true, 'message' => $e->getMessage(), 'type' => 'error'];
                    } catch (\RuntimeException $e) {
                        return ['terminate' => true, 'message' => $e->getMessage(), 'type' => 'error'];
                    } catch (\Exception $e) {
                        return ['terminate' => true, 'message' => 'An unexpected error occurred during login.', 'type' => 'error'];
                    }
                }
            );

        $result = $wizard->process($input, $commandId);
        return $result;
    }
}