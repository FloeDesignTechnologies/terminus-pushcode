# Terminus Push Code

[![Terminus v1.x Compatible](https://img.shields.io/badge/terminus-v1.x-green.svg)](https://github.com/pantheon-systems/terminus-build-tools-plugin/tree/1.x)

A Terminus plugin providing a single command to push code in the current working directory to a Pantheon environment.

This command is intended to be used in development workflow where Pantheon is not used as the main Git repository.
Instead, the main Git repository contains sources used to build what needs to be deployed on Pantheon (eg. a project
using Composer without commiting the vendor directory). This command can then be used after a complete build to
push its result to a Pantheon environment.

## Configuration

When pushing code to Pantheon, using Git, the command rely on a `.pantheonignore` file in the current folder. This
file list the files and directories to ignore while pushing chnages to Pantheon ()just like a `.gitignore` file).

## Help

Run `terminus help push-code` to get help.