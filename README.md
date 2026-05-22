# Puchiko
<img width="248" height="182" alt="nyu" src="https://github.com/user-attachments/assets/8e117566-2828-4b02-8b62-61a227a7ff61" />

## Introduction
Ever started a new PHP project and dread re-implementing basic, repetitive code every damn time? Ever copy-pasted code snippets and files from one project to the next? Look no further nyu.

Puchiko is a PHP package that provides a variety of QoL features that arent available in vanilla PHP, included but not limited to:
1. Feature that allows running background processes fully independent of the host request, useful for intensive databae actions.
1. DOM manipulation functions via `DOMDocument` and clean HTML truncation.
1. Fetching the dimensions of compressed Shockwave Flash files
1. Trove of string checking, normalization, and manipulation functions (building anchor tags from plain URLs and more)
1. Function for building clean URLs from complex multi-parameter form inputs
1. Set of functions for interfacting with the file system. Including a way to remove GPS data from jpeg images using `exiftool` and creating video thumbnails using `ffmpeg`
1. Json respone functions along with cache controls, useful for API endpoints.

Its designed for the collection of scripts maintained by Heyuri (kokonotsuba, TWINTAIL UPLOADER, ksphp+) but can be deployed on any project. Its particularly useful on a script focused on user-generated content, as sanitization and DOM code is rarely ever needed to be different from project-to-project.

## Installation
Some functions in Puchiko do require external tools, namely `ffmpeg` and `exiftool`, you can intall them from your operating system's package manager.

To include this in your project you just need to add the following entries to composer.json:
- Add `heyuri/puchiko": "dev-main"` to `require` 
- Add `"url": "https://github.com/Heyuri/Puchiko"` to `repositories`

It should look like this:
```json
{
    "name": "heyuri/kokonotsuba",
    "description": "Kokonotsuba imageboard software",
    "type": "project",
    "license": "MIT",
    "require": {
        "php": ">=8.4",
        "heyuri/puchiko": "dev-main"
    },
    "minimum-stability": "dev",
    "prefer-stable": true,
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Heyuri/Puchiko"
        }
    ],
    "config": {
        "vendor-dir": "vendor"
    }
}
```

then run `composer update` on a pre-existing project or `composer install` on a fresh one.
