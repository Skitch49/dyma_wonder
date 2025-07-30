<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Question;
use App\Entity\Vote;
use App\Form\CommentForm;
use App\Form\QuestionForm;
use App\Repository\CommentRepository;
use App\Repository\QuestionRepository;
use App\Repository\VoteRepository;
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
    public function show(Request $request, QuestionRepository $questionRepo,int $id, EntityManagerInterface $em): Response
    {
        $question = $questionRepo->getQuestionWithCommentsAndAuthors($id);

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
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function ratingQuestion(Request $request, Question $question, VoteRepository $voteRepo, int $score, EntityManagerInterface $em)
    {
        $user = $this->getUser();

        // si utilisateur pas propiétaire de la question
        if ($user !== $question->getAuthor()) {

            //
            $vote = $voteRepo->findOneBy(['author' => $user, 'question' => $question]); 
            // Si il a déjà voté
            if ($vote) {
                // Si il a déja voté et revote pour la meme chose on supprime le vote
                if ($vote->getIsLiked() && $score > 0 || (!$vote->getIsLiked() && $score < 0)) {
                    $em->remove($vote);
                    $question->setRating($question->getRating() + ($score > 0 ? -1 : 1));
                }
                // Si il a déja voté mais vote l'inverse
                else {
                    $vote->setIsLiked(!$vote->getIsLiked());
                    $question->setRating($question->getRating() + ($score > 0 ? 2 : -2));
                }
            }
            // Si il n'a pas déja voté
            else {
                $vote = new Vote();
                $vote->setAuthor($user)
                    ->setQuestion($question);

                $vote->setIsLiked($score > 0 ? true : false);
                $question->setRating($question->getRating() + $score);
                $em->persist($vote);
            }

            $em->flush();
        } else {
            $this->addFlash('error', 'Vous ne pouvez pas noté votre propre question !');
        }


        $referer = $request->server->get('HTTP_REFERER');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');
    }

    #[Route('/comment/rating/{id}/{score}', name: 'comment_rating')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function ratingComment(Request $request, Comment $comment, VoteRepository $voteRepo, int $score, EntityManagerInterface $em)
    {
        $user = $this->getUser();

        // si utilisateur pas propiétaire de la réponse
        if ($user !== $comment->getAuthor()) {

            //
            $vote = $voteRepo->findOneBy(['author' => $user, 'comment' => $comment]);
            // Si il a déjà voté
            if ($vote) {
                // Si il a déja voté et revote pour la meme chose on supprime le vote
                if ($vote->getIsLiked() && $score > 0 || (!$vote->getIsLiked() && $score < 0)) {
                    $em->remove($vote);
                    $comment->setRating($comment->getRating() + ($score > 0 ? -1 : 1));
                }
                // Si il a déja voté mais vote l'inverse
                else {
                    $vote->setIsLiked(!$vote->getIsLiked());
                    $comment->setRating($comment->getRating() + ($score > 0 ? 2 : -2));
                }
            }
            // Si il n'a pas déja voté
            else {
                $vote = new Vote();
                $vote->setAuthor($user)
                    ->setComment($comment);

                $vote->setIsLiked($score > 0 ? true : false);
                $comment->setRating($comment->getRating() + $score);
                $em->persist($vote);
            }

            $em->flush();
        } else {
            $this->addFlash('error', 'Vous ne pouvez pas noté votre propre réponse !');
        }

        $referer = $request->server->get('HTTP_REFERER');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');
    }
}
