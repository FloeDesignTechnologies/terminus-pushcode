<?php

namespace Floe\Terminus\Commands;


use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Models\Environment;
use Pantheon\Terminus\Models\Site;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Robo\Collection\CollectionBuilder;
use Robo\Contract\BuilderAwareInterface;
use Robo\TaskAccessor;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

define('DEFAULT_DOT_PANTHEONIGNORE', <<<EOF
# File ignored when pushing code to Pantheon (use .gitignore syntax)
# Ignore all hidden files and folders.
/.*
!/.gitignore

EOF
);

class PushCodeCommand extends TerminusCommand implements SiteAwareInterface, BuilderAwareInterface
{
    use SiteAwareTrait;

    // Allow usage of Robo tasks
    use TaskAccessor;
    // Load used Robo tasks
    // Load used Robo tasks
    use \Robo\Task\Filesystem\loadTasks;
    use \Robo\Task\Vcs\loadTasks;

    /**
     * Set the connection mode to 'sftp' or 'git' mode, and wait for
     * it to complete.
     *
     * @param Environment $env
     * @param string $mode
     */
    protected function connectionSet($env, $mode)
    {
        $workflow = $env->changeConnectionMode($mode);
        if (is_string($workflow)) {
            $this->log()->notice($workflow);
        } else {
            while (!$workflow->checkProgress()) {
                // TODO: (ajbarry) Add workflow progress output
            }
            $this->log()->notice($workflow->getMessage());
        }
    }

    /**
     * Return whether or not a branch exists on a remote repository.
     *
     * @param string $url
     *   The remote repository URL
     * @param string $branch
     *   The branch name
     */
    protected function gitBranchExists($url, $branch)
    {
        $url = escapeshellarg($url);
        $branch = escapeshellarg($branch);
        $output = [];
        exec("git ls-remote --heads --exit-code $url $branch", $output, $exit_code);
        return !$exit_code;
    }

    /**
     * Push code in the current working directory to a Pantheon environment.
     *
     *
     * Code push to Pantheon is done with Git, the theoretical behavior of this
     * command is to
     *  1. Checkout the HEAD of the branch for the pushed to environment to a
     *     temporary directory
     *  2. Update this temporary directory to contains exactly what we want to
     *     push to Pantheon (ie. adding/removing/updating all the needed files)
     *  3. Commit all the changes
     *  4. Push the changes to Pantheon
     *
     * To avoid the resources hungry and slow process of coping files to a
     * temporary directory, step 2 is is done in an non-obvious way. Instead of
     * copying the files over the temporary fresh clone, the `.git` of the
     * current working directory is replaced with the one from the fresh clone.
     * Also, the `.gitignore` files is temporally overridden with the
     * `.pantheonignore` file as a way to control what is pushed to Pantheon.
     * This allow Git commands in the working directory to act on a clone of
     * the the Pantheon repo and then push to it. Once the command complete
     * (whether on a success or a failure), the original `.git` and
     * `.gitignore` are restored.
     *
     * The pushed to environment does not need to actually exists. If it does
     * not, the command create a new Git branch on Pantheon but not a new
     * environment.
     *
     *
     * @command push-code
     * @authorize
     *
     * @param string $site_env
     *   The site/environment to push code to <site>[.<env>]. If the environment
     *   is omitted, `dev` will be used.
     * @option $msg|m The commit message used for the push. Either a string or as
     *   a filename prefixed with '@'.
     * @option $confirm Pause the command execution right before pushing changes
     *   to the remote Pantheon repository.
     */
    public function pushCode($site_env, $opts = [
        'msg|m' => '',
        'confirm' => false,
    ])
    {
        list($site, $env) = explode('.', $site_env, 2);
        if (empty($env)) {
            $env = 'dev';
        }
        if (empty($site) || empty($env)) {
            throw new TerminusException('The environment argument must be given as <site_name>.<environment>');
        }
        if (in_array($env, ['test', 'live'])) {
            throw new TerminusException(
                'Cannot push code to the {env} environment',
                ['env' => $env->id,]
            );
        }

        /** @var Site $site */
        $site = $this->getSite($site);

        // The branch to push to
        $dest_branch = $env == 'dev' ? 'master' : $env;

        // If the environment does exist, then we need to be in git mode
        // to push the branch up to the existing site.
        if ($site->getEnvironments()->has($env)) {
            // Get a reference to our target site.
            $this->connectionSet($env, 'git');
        }

        // The URL of the Pantheon git repo for the site.
        $git_url = $site->getEnvironments()->get('dev')->gitConnectionInfo()['url'];

        // The branch checked-out from Pantheon
        $checkout_branch = $this->gitBranchExists($git_url, $dest_branch) ? $dest_branch : 'master';

        // The suffix used for backup files.
        $backup_suffix = '.' . time();

        // The commit message used when committing changes to be pushed to Pantheon.
        if (!empty($opts['msg'])) {
            if ($opts['msg'][0] == '@' && file_exists($filename = substr($opts['msg'], 1))) {
                $commit_msg = file_get_contents($filename);
            } else {
                $commit_msg = $opts['msg'];
            }
        }
        else {
            $commit_msg = "";
        }

        if (!file_exists('.pantheonignore')) {
            $this->log()->warning("Creating missing .pantheonignore file.");
            file_put_contents('.pantheonignore', DEFAULT_DOT_PANTHEONIGNORE);
        }

        /** @var CollectionBuilder $collection */
        $collection = $this->collectionBuilder();

        $pantheon_clone = $collection->tmpDir();

        $collection->taskGitStack()
            ->exec(['clone', '--depth 1', $git_url, $pantheon_clone, '-b', $checkout_branch]);

        // Backup and override files and folders.
        $collection->addTask($this->taskFilesystemStack()
            // Backup overridden files and folders.
            ->rename('.git', '.git' . $backup_suffix)
            ->rename('.gitignore', '.gitignore' . $backup_suffix)
            // Override files and folders.
            ->symlink($pantheon_clone . "/.git", '.git')
            ->copy('.pantheonignore', '.gitignore')
        );

        // Restore overridden files on completion.
        // This is registered now so it will always run whether or not the next
        // tasks succeed.
        $collection->completion($this->taskFilesystemStack()
            // Remove overridden files and folders.
            ->remove('.git')
            ->remove('.gitignore')
            // Restore backups.
            ->rename('.git' . $backup_suffix, '.git')
            ->rename('.gitignore' . $backup_suffix, '.gitignore')
        );

        // Commit the current content of the working directory.
        $git_stack = $collection->taskGitStack();
        // Specifically add any folder with a .git folder using a trailing slash
        // to avoid adding them as submodule.
        // See http://debuggable.com/posts/git-fake-submodules:4b563ee4-f3cc-4061-967e-0e48cbdd56cb
        // and http://stackoverflow.com/questions/2317652/nested-git-repositories-without-submodules#2317870
        $finder = new Finder();
        /** @var SplFileInfo $dir */
        foreach (
            $finder->directories()
                     ->in(getcwd())
                     ->ignoreDotFiles(false)
                     ->ignoreVCS(false)
                     ->depth('> 0')
                     ->name('.git')
            as $dir) {
            $git_stack->add('"' . substr($dir->getRelativePath(), 0, -4) . '"');
        }
        $git_stack
            ->exec(['add', '--all', '.'])
            ->commit($commit_msg, "-a");

        if ($opts['confirm']) {
            $command = $this;
            $collection->addCode(function () use ($command) {
                return $command->confirm("Confirm code push?") ? 0 : 1;
            });
        }

        $collection->taskGitStack()
            ->exec(['push', 'origin', "$checkout_branch:$dest_branch", '--tags']);

        // Execute and clean up the task collection.
        return $collection->run();
    }
}