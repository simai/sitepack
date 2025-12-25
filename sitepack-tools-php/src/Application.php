<?php

declare(strict_types=1);

namespace SitePack;

use SitePack\Command\ValidateEnvelopeCommand;
use SitePack\Command\ValidatePackageCommand;
use SitePack\Command\ValidateVolumeSetCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct('sitepack-validate', '0.4.0');

        $this->add(new ValidatePackageCommand());
        $this->add(new ValidateEnvelopeCommand());
        $this->add(new ValidateVolumeSetCommand());
        $this->setDefaultCommand('package', true);
    }
}
