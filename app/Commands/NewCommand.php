<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class NewCommand extends Command
{
    private string $repository = "https://github.com/laranex/api-starter-kit";
    private string $branch = "master";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "new
                            {name : The name of the Application (required)}
                            {--c|configure : Configure the application initializing}
                            {--f|force : Forces install even if the directory already exists}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Create a new Laravel Application by Laranex";

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $name = $this->argument("name");
        if ($this->option("force") && File::exists($name)) {
            exec("rm -rf " . $name);
        }

        if ($this->option("configure")) {
            $this->configureRepository();
        } else {
            $this->installApplication();
        }

        $this->welcomeUser();
    }

    /**
     * Configure the GitHub repository.
     *
     * @return void
     */
    protected function configureRepository(): void
    {
        $this->task("Configuring the initializing ", function () {
            $this->chooseBranch();
        });

        $this->installApplication();
    }

    /**
     * Clone the GitHub repository.
     *
     * @return void
     */
    protected function installApplication(): void
    {
        $name = $this->argument("name");

        $this->task("Creating a project at ./$name ", function () use ($name) {
            $process = new Process(["git", "clone", "-b", $this->branch, "--single-branch", $this->repository, $name]);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->error("> Failed to install the application: " . $process->getErrorOutput());
                exit(1);
            }
            return true;
        });


        chdir($name);
        $this->task("Configuring the project ", function () use ($name) {
            exec("git remote remove origin");
            exec("rm -rf .git .gitignore");
        });

        $this->task("Installing composer dependencies", function () {
            $process = new Process(["composer", "install"], timeout: 300);
            $process->run(function ($_, $buffer) {
                $this->info($buffer);
            });
        });
    }

    /**
     * Get the branch
     *
     * @return void
     */
    protected function chooseBranch(): void
    {
        $process = new Process(['git', 'ls-remote', '--heads', $this->repository]);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Failed to get available branches: ' . $process->getErrorOutput());
            exit(1);
        }

        $branches = array_map(function ($line) {
            return trim(substr($line, strrpos($line, '/') + 1));
        }, explode("\n", trim($process->getOutput())));

        $this->branch = $this->choice("Select a branch", $branches);
    }


    /**
     * Welcome User
     *
     * @return void
     */
    protected function welcomeUser(): void
    {
        $this->newLine();
        $this->line(" <bg=blue;fg=white> INFO </> Application ready! <options=bold>Build something amazing.</>");
        $this->line(" <bg=blue;fg=white> INFO </> Documentation can be found at $this->repository.</>");
    }
}
