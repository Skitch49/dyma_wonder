<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Question;
use App\Form\CommentForm;
use App\Form\QuestionForm;
use App\Repository\CommentRepository;
use App\Repository\QuestionRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/question', name: 'question_')]
class QuestionController extends AbstractController
{
    #[Route('/ask', name: 'form')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $question = new Question();
        $formQuestion = $this->createForm(QuestionForm::class, $question);

        $formQuestion->handleRequest($request);

        if ($formQuestion->isSubmitted() && $formQuestion->isValid()) {

            $question->setNbrOfResponse(0)
                ->setRating(0)
                ->setCreatedAt(new DateTimeImmutable())
                ->setAuthor($this->getUser());
            $em->persist($question);
            $em->flush();
            $this->addFlash('success', 'Votre question à été ajoutée avec succès');
            return $this->redirectToRoute("home");
        }

        return $this->render('question/index.html.twig', [
            'form' => $formQuestion->createView(),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Request $request, Question $question, EntityManagerInterface $em): Response
    {
        $options = [
            'question' => $question
        ];
        $user = $this->getUser();

        if ($user) {

            $comment = new Comment();
            $commentForm = $this->createForm(CommentForm::class, $comment);

            $commentForm->handleRequest($request);

            if ($commentForm->isSubmitted() && $commentForm->isValid()) {
                $comment->setCreatedAt(new DateTimeImmutable())
                    ->setQuestion($question)
                    ->setRating(0)
                    ->setAuthor($user);
                $question->setNbrOfResponse($question->getNbrOfResponse() + 1);
                $em->persist($comment);
                $em->flush();
                $this->addFlash('success', 'Commentaire ajouté avec succès');
                return $this->redirect($request->getUri());
            }
            $options['commentForm'] = $commentForm->createView();
        }



        return $this->render('question/show.html.twig', $options);
    }

    #[Route('/my-questions', name: 'my_questions')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function myQuestions(QuestionRepository $questionRepo): Response
    {
        $questions = $questionRepo->findBy(['author' => $this->getUser()], ['createdAt' => 'DESC']);
        return $this->render('question/my_questions.html.twig', ['questions' => $questions]);
    }

    #[Route('/my-responses', name: 'my_responses')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function myComments(CommentRepository $commentRepo): Response
    {
        $comments = $commentRepo->findBy(['author' => $this->getUser()], ['createdAt' => 'DESC']);
        return $this->render('question/my_comments.html.twig', ['comments' => $comments]);
    }

    #[Route('/rating/{id}/{score}', name: 'rating')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function ratingQuestion(Request $request, Question $question, int $score, EntityManagerInterface $em)
    {
        $question->setRating($question->getRating() + $score);
        $em->flush();
        $referer = $request->server->get('HTTP_REFERER');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');
    }

    #[Route('/comment/rating/{id}/{score}', name: 'comment_rating')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function ratingComment(Request $request, Comment $comment, int $score, EntityManagerInterface $em)
    {
        $comment->setRating($comment->getRating() + $score);
        $em->flush();
        $referer = $request->server->get('HTTP_REFERER');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');
    }
}
