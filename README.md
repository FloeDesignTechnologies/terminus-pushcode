# Terminus Push Code

![Terminus v1.x Compatible](https://img.shields.io/badge/terminus-v1.x-green.svg)

[![Packagist](https://img.shields.io/packagist/l/floe/terminus-pushcode.svg)](https://raw.githubusercontent.com/FloeDesignTechnologies/terminus-pushcode/master/LICENSE)

A Terminus plugin providing a single command to push code in the current working directory to a Pantheon environment.

This command is intended to be used in development workflow where Pantheon is not used as the main Git repository.
Instead, the main Git repository contains sources used to build what needs to be deployed on Pantheon (eg. a project
using Composer without commiting the vendor directory). This command can then be used after a complete build to
push its result to a Pantheon environment.

## Installation

Install as a Terminus plugin (see https://pantheon.io/docs/terminus/plugins/).

Code push to Pantheon is done with Git, this plugin requires a `git` executable usable from PHP (usually, being able to run `git` on the command line is all that is needed).

## Configuration

When pushing code to Pantheon, using Git, the command rely on a `.pantheonignore` file in the current folder. This
file list the files and directories to ignore while pushing chnages to Pantheon ()just like a `.gitignore` file).

## Help

Run `terminus help push-code` to get help.

## How it works

Code push to Pantheon is done with Git, the theoretical behavior of this command is to

1. Checkout the HEAD of the branch for the pushed to environment to a temporary directory
2. Update this temporary directory to contains exactly what we want to push to Pantheon (ie. adding/removing/updating
   all the needed files)
3. Commit all the changes
4. Push the changes to Pantheon

To avoid the resources hungry and slow process of coping files to a temporary directory, step 2 is is done in an
non-obvious way. Instead of copying the files over the temporary fresh clone, the `.git` of the current working
directory is replaced with the one from the fresh clone. Also, the `.gitignore` files is temporally overridden with the
`.pantheonignore` file as a way to control what is pushed to Pantheon. This allow Git commands in the working directory
to act on a clone of the the Pantheon repo and then push to it. Once the command complete (whether on a success or a
failure), the original `.git` and `.gitignore` are restored.

The pushed to environment does not need to actually exists. If it does not, the command create a new Git branch on
Pantheon but not a new environment.
