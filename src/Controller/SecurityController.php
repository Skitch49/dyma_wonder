<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserForm;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

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
}
