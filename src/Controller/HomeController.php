<?php

namespace App\Controller;

use App\Entity\Question;
use App\Repository\QuestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index( QuestionRepository $questionRepo): Response
    {

        $questions = $questionRepo->getQuestionsWithAuthors();
                    
         

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'questions' => $questions
        ]);
    }
}
