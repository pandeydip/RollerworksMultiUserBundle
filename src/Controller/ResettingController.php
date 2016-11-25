<?php

/*
 * This file is part of the RollerworksMultiUserBundle package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Rollerworks\Bundle\MultiUserBundle\Controller;

use FOS\UserBundle\Model\User;
use FOS\UserBundle\FOSUserEvents;
use Symfony\Component\HttpFoundation\Request;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\Util\TokenGeneratorInterface;
use FOS\UserBundle\Event\GetResponseNullableUserEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use FOS\UserBundle\Controller\ResettingController as BaseResettingController;

class ResettingController extends BaseResettingController
{

    public function checkEmailAction(Request $request)
    {
        $userDiscriminator = $this->container->get('rollerworks_multi_user.user_discriminator');
        $username = $request->query->get('username');

        if (empty($username)) {
            // the user does not come from the sendEmail action
            return new RedirectResponse($this->container->get('router')->generate($userDiscriminator->getCurrentUserConfig()->getRoutePrefix() . '_resetting_request'));
        }

        return $this->render('FOSUserBundle:Resetting:check_email.html.twig', [
            'tokenLifetime' => floor($this->container->getParameter('fos_user.resetting.token_ttl') / 3600),
        ]);

//        return $this->container->get('templating')->renderResponse('FOSUserBundle:Resetting:checkEmail.html.twig', [
//            'email' => $email,
//        ]);
    }

    public function sendEmailAction(Request $request)
    {
        $userDiscriminator = $this->container->get('rollerworks_multi_user.user_discriminator');
        $username = $request->request->get('username');
        /** @var User $user */
        $user = $this->container->get('fos_user.user_manager')->findUserByUsernameOrEmail($username);


        /** @var $dispatcher EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');

        /* Dispatch init event */
        $event = new GetResponseNullableUserEvent($user, $request);
        $dispatcher->dispatch(FOSUserEvents::RESETTING_SEND_EMAIL_INITIALIZE, $event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        $ttl = $this->container->getParameter('fos_user.resetting.token_ttl');

        if (null !== $user && !$user->isPasswordRequestNonExpired($ttl)) {
            $event = new GetResponseUserEvent($user, $request);
            $dispatcher->dispatch(FOSUserEvents::RESETTING_RESET_REQUEST, $event);

            if (null !== $event->getResponse()) {
                return $event->getResponse();
            }

            if (null === $user->getConfirmationToken()) {
                /** @var $tokenGenerator TokenGeneratorInterface */
                $tokenGenerator = $this->get('fos_user.util.token_generator');
                $user->setConfirmationToken($tokenGenerator->generateToken());
            }

            /* Dispatch confirm event */
            $event = new GetResponseUserEvent($user, $request);
            $dispatcher->dispatch(FOSUserEvents::RESETTING_SEND_EMAIL_CONFIRM, $event);

            if (null !== $event->getResponse()) {
                return $event->getResponse();
            }

            $this->get('fos_user.mailer')->sendResettingEmailMessage($user);
            $user->setPasswordRequestedAt(new \DateTime());
            $this->get('fos_user.user_manager')->updateUser($user);

            /* Dispatch completed event */
            $event = new GetResponseUserEvent($user, $request);
            $dispatcher->dispatch(FOSUserEvents::RESETTING_SEND_EMAIL_COMPLETED, $event);

            if (null !== $event->getResponse()) {
                return $event->getResponse();
            }
        }

        return new RedirectResponse($this->container->get('router')->generate(
            $userDiscriminator->getCurrentUserConfig()->getRoutePrefix() . '_resetting_check_email',
            ['username' => $username]));


//        if (null === $user) {
//            return $this->container->get('templating')->renderResponse('FOSUserBundle:Resetting:request.html.twig',
//                ['invalid_username' => $username]);
//        }
//
//        if ($user->isPasswordRequestNonExpired($userDiscriminator->getCurrentUserConfig()->getConfig('resetting.token_ttl'))) {
//            return $this->container->get('templating')->renderResponse('FOSUserBundle:Resetting:passwordAlreadyRequested.html.twig');
//        }
//
//        if (null === $user->getConfirmationToken()) {
//            $tokenGenerator = $this->container->get('fos_user.util.token_generator');
//            $user->setConfirmationToken($tokenGenerator->generateToken());
//        }
//
//        $this->container->get('fos_user.mailer')->sendResettingEmailMessage($user);
//        $user->setPasswordRequestedAt(new \DateTime());
//        $this->container->get('fos_user.user_manager')->updateUser($user);
//
//        return new RedirectResponse($this->container->get('router')->generate(
//            $userDiscriminator->getCurrentUserConfig()->getRoutePrefix() . '_resetting_check_email',
//            ['email' => $this->getObfuscatedEmail($user)]
//        ));
    }
}
