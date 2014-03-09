<?php

/*
 * This file is part of the RollerworksMultiUserBundle package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rollerworks\Bundle\MultiUserBundle\Command;

use FOS\UserBundle\Command\ActivateUserCommand as BaseActivateUserCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class ActivateUserCommand extends BaseActivateUserCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        parent::configure();

        $definition = $this->getDefinition();
        $definition->addArgument(
            new InputArgument('user-system', InputArgument::REQUIRED, 'The user-system to use')
        );

        $this
            ->setHelp(<<<EOT
The <info>fos:user:activate</info> command activates a user (so they will be able to log in):

  <info>php app/console fos:user:activate --user-system=acme_user matthieu</info>
EOT
            );
    }

    /**
     * @see Command
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        /** @var UserDiscriminatorInterface $discriminator */
        $discriminator = $this->getContainer()->get('rollerworks_multi_user.user_discriminator');
        $discriminator->setCurrentUser($input->getArgument('user-system'));

        parent::interact($input, $output);
    }
}