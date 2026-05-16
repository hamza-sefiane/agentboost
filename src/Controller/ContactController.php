<?php

namespace App\Controller;


use App\Entity\User;
use App\Form\ContactType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly string $contactEmail,
    ) {}

    #[Route('/contact', name: 'app_contact')]
    public function index(
        Request $request,
        MailerInterface $mailer,
    ): Response {
        $form = $this->createForm(ContactType::class);

        /** @var User|null $user */
        $user = $this->getUser();

        if ($user) {
            $form->setData([
                'name' => $user->getCompanyName() ?: '',
                'email' => $user->getEmail(),
            ]);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $email = (new Email())
                ->from($this->contactEmail)
                ->replyTo($data['email'])
                ->to($this->contactEmail)
                ->subject('[AgentBoost] ' . $data['subject'])
                ->text(
                    "Nom : {$data['name']}\n\n" .
                        "Email : {$data['email']}\n\n" .
                        "Sujet : {$data['subject']}\n\n" .
                        "Message :\n{$data['message']}"
                );

            $mailer->send($email);

            $this->addFlash(
                'success',
                'Votre message a bien été envoyé.'
            );

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig', [
            'contactForm' => $form->createView(),
        ]);
    }
}
