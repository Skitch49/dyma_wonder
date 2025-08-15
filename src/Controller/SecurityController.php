<?php

namespace App\Controller;

use App\Entity\ResetPassword;
use App\Entity\User;
use App\Form\UserForm;
use App\Repository\ResetPasswordRepository;
use App\Repository\UserRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class SecurityController extends AbstractController
{
    #[Route('/signup', name: 'signup')]
    public function signup(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer): Response
    {
        $user = new User();
        $userForm = $this->createForm(UserForm::class, $user);
        $userForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            $user->setPassword($passwordHasher->hashPassword($user, $user->getPassword()))
                ->setCreatedAt(new DateTimeImmutable());
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', "Bienvenue sur Wonder {$user->getFirstname()} !");

            $email = new TemplatedEmail();
            $email->to($user->getEmail())
                ->subject("Bienvenue sur wonder")
                ->htmlTemplate("@email_templates/welcome.html.twig")
                ->context(['username' => $user->getFirstname()]);
            $mailer->send($email);

            return $this->redirectToRoute('login');
        }

        return $this->render('security/signup.html.twig', [
            'userForm' => $userForm->createView(),
        ]);
    }

    #[Route('/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // si l'utilisateur est connnecter
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $username = $authenticationUtils->getLastUsername();
        return $this->render('security/login.html.twig', [
            'error' => $error,
            'username' => $username
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout() {}

    #[Route('/reset-password/{token}', name: 'reset-password')]
    public function resetPassword(RateLimiterFactory $passwordRecoveryLimiter, Request $request, string $token, ResetPasswordRepository $resetPasswordRepository, EntityManagerInterface $em, UserPasswordHasherInterface $userPasswordHasher)
    {
        $limiter = $passwordRecoveryLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Vous devez attendre 1 heure pour refaire une tentative !');
            $this->redirectToRoute('login');
        }

        $resetPassword = $resetPasswordRepository->findOneBy(['token' => sha1($token)]);
        if (!$resetPassword || $resetPassword->getExpiredAt() < new DateTime('now')) {
            if ($resetPassword) {
                $em->remove($resetPassword);
                $em->flush();
            }
            $this->addFlash('error', 'Votre demande est expiré veuillez refaire une demande.');
            return $this->redirectToRoute('login');
        }

        $passwordForm = $this->createFormBuilder()
            ->add('password', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'constraints' => [
                    new NotBlank(message: 'Le mot de passe ne doit pas être vide.'),
                    new Length(min: 6, minMessage: 'Le mot de passe doit faire au moins 6 caractères !')
                ]
            ])->getForm();

        $passwordForm->handleRequest($request);
        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $user = $resetPassword->getUser();
            $password = $passwordForm->get('password')->getData();
            $passwordHash = $userPasswordHasher->hashPassword($user, $password);
            $user->setPassword($passwordHash);
            $em->remove($resetPassword);
            $em->flush();
            $this->addFlash('success', 'Votre mot de passe a été modifié.');
            $this->redirectToRoute('login');
        }

        return $this->render('security/reset_password_form.html.twig', ['form' => $passwordForm->createView()]);
    }


    #[Route('/reset-password-request', name: 'reset-password-request')]
    public function resetPasswordRequest(RateLimiterFactory $passwordRecoveryLimiter, Request $request, UserRepository $userRepository, ResetPasswordRepository $resetPasswordRepository, EntityManagerInterface $em, MailerInterface $mailer)
    {
        $limiter = $passwordRecoveryLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Vous devez attendre 1 heure pour refaire une tentative !');
            $this->redirectToRoute('login');
        }

        $emailForm = $this->createFormBuilder()->add('email', EmailType::class, [
            'constraints' => [
                new NotBlank(message: 'Veuillez renseigner votre email')
            ],
            'required' => true,
        ])->getForm();

        $emailForm->handleRequest($request);
        if ($emailForm->isSubmitted() && $emailForm->isValid()) {
            $email = $emailForm->get('email')->getData();
            $user = $userRepository->findOneBy(['email' => $email]);
            if ($user) {

                $resetPassword = $resetPasswordRepository->findOneBy(['user' => $user]);

                // Si il n'y a pas de resetPassword sur l'utilisateur on en créer un
                if (! $resetPassword) {
                    $resetPassword = new ResetPassword();
                    $resetPassword->setUser($user);
                }


                $resetPassword->setExpiredAt(new \DateTimeImmutable('+2 hours'));

                // substr pour récupérer une portion du token car il peut etre plus court du aux caractères spéciaux
                // remplace caratère spéciaux qui pose probleme dans une url par rien et convertie une chaine de 20 bytes aléatoire en base64

                $token = substr(str_replace(['+', '\\', '/', '='], '', base64_encode(random_bytes(30))), 0, 20);
                $resetPassword->setToken(sha1($token));
                $em->persist($resetPassword);
                $em->flush();

                $emailSend = new TemplatedEmail();
                $emailSend->to($email)
                    ->subject('Demande de réinitialisation de mot de passe Wonder')
                    ->htmlTemplate('@email_templates/reset_password_request.html.twig')
                    ->context([
                        'username' => $user->getFirstname(),
                        'token' => $token
                    ]);

                $mailer->send($emailSend);
            }
            $this->addFlash('success', 'Un email vous a été envoyé pour réinitialiser votre mot de passe');
            $this->redirectToRoute('home');
        }

        return $this->render('security/reset_password_request.html.twig', [
            'form' => $emailForm->createView()
        ]);
    }
}
