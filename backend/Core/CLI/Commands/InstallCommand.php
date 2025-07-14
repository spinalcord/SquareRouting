<?php

namespace SquareRouting\Core\CLI\Commands;
use  SquareRouting\Core\CLI\CommandRegistry;
use  SquareRouting\Core\CLI\StepFeedback;
use  SquareRouting\Core\CLI\CommandWizard;

class InstallCommand extends BaseCommand
{
    public function getName(): string
    {
        return 'install';
    }

    public function getDescription(): string
    {
        return 'Install the system and create admin account';
    }

    public function execute(array $args, string $input, string $commandId): array
    {
        
        
        if ($this->dotEnv->get('SYSTEM_MARKED_AS_INSTALLED') == true) {
            return $this->createResponse('System is already installed.', 'warning');
        }

        $wizard = new CommandWizard('create_admin_account');
        $wizard
            ->addStep(
                'Enter your emailaddress:',
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
                'Enter your username:',
                function ($input, $data) {
                    $stepValidation = new \SquareRouting\Core\Validation\Validator(
                        ['username' => $input], 
                        ['username' => [
                            new \SquareRouting\Core\Validation\Rules\AlphaNumeric, 
                            new \SquareRouting\Core\Validation\Rules\Min(2), 
                            new \SquareRouting\Core\Validation\Rules\Max(30)
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
                    return ['username' => trim($input)];
                }
            )
            ->addStep(
                'Enter your password:',
                function ($input, $data) {
                    $stepValidation = new \SquareRouting\Core\Validation\Validator(
                        ['password' => $input], 
                        ['password' => [new \SquareRouting\Core\Validation\Rules\Password]]
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
                    return 'Erstelle Administrator...';
                },
                function ($input, $data) {
                    $this->account->register($data['email'], $data['password'], ['username' => $data['username']]);
                    $this->account->login($data['email'], $data['password']);
                    $this->account->assignRole($this->account->getCurrentUserId(), \SquareRouting\Core\Schema\Role::ADMIN->value);
                    $this->dotEnv->set('SYSTEM_MARKED_AS_INSTALLED',true);
                    $this->dotEnv->save();
                    return ['config' => 'loaded'];
                }
            );

        $result = $wizard->process($input, $commandId);
        return $result;
    }
}