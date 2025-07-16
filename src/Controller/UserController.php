<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserController extends AbstractController
{
    #[Route('/user', name: 'current_user')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function currentUserProfile(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $userForm = $this->createForm(UserForm::class, $user);
        $userForm->remove('password');
        $userForm->add('newPassword', PasswordType::class, ['label' => 'Nouveau mot de passe', 'required' => false]);
        $userForm->handleRequest($request);
        if ($userForm->isSubmitted() && $userForm->isValid()) {

            $newPassword = $user->getNewPassword();
            if ($newPassword) {
                $hash = $userPasswordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hash);
            }
            $em->flush();
            $this->addFlash('success', 'Modifications sauvegardées !');
        }

        return $this->render('user/index.html.twig', [
            'form' => $userForm->createView()
        ]);
    }

    #[Route('/user/{id}', name: 'user')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function userProfile(User $user): Response
    {
        $currentUser = $this->getUser();
        if ($user === $currentUser) {
            return $this->redirectToRoute('current_user');
        }
        return $this->render('user/user.html.twig', [
            'user' => $user,
        ]);
    }
}
