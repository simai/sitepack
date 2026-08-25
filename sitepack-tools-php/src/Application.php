<?php

declare(strict_types=1);

namespace SitePack;

use SitePack\Command\ValidateEnvelopeCommand;
use SitePack\Command\ValidatePackageCommand;
use SitePack\Command\ValidateVolumeSetCommand;
use SitePack\Command\SignFileCommand;
use SitePack\Command\VerifySignatureCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct('sitepack-validate', '1.1.0');

        $this->add(new ValidatePackageCommand());
        $this->add(new ValidateEnvelopeCommand());
        $this->add(new ValidateVolumeSetCommand());
        $this->add(new SignFileCommand());
        $this->add(new VerifySignatureCommand());
        $this->setDefaultCommand('package');
    }
}
