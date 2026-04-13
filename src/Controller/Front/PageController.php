<?php
namespace App\Controller\Front;

use App\Form\ContactType;
use App\Model\ContactMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    private const STORE_NAME = 'NUTRI CITY';
    private const STORE_ADDRESS_LINES = ['7 Pl. du Marché Neuf', '67000 Strasbourg', 'France'];
    private const STORE_PHONE = '+33 1 23 45 67 89';
    private const STORE_EMAIL = 'contact@nutricity.fr';
    private const GOOGLE_MAPS_EMBED_URL = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2639.436778328013!2d7.745639276743508!3d48.58233522009344!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4796c9ac1a4a8d9f%3A0x4fdc6aec5208e080!2sNUTRI%20CITY!5e0!3m2!1sen!2sfr!4v1772103742057!5m2!1sen!2sfr';

    #[Route('/a-propos', name: 'about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('front/pages/about.html.twig', [
            'store' => $this->storePayload(),
        ]);
    }

    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function contact(Request $request, MailerInterface $mailer): Response
    {
        $message = new ContactMessage();
        $form = $this->createForm(ContactType::class, $message);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (new Email())
                ->from(sprintf('%s <%s>', self::STORE_NAME, self::STORE_EMAIL))
                ->replyTo($message->getEmail())
                ->to(self::STORE_EMAIL)
                ->subject('[Contact] ' . $message->getSubject())
                ->text(
                    "Nom: {$message->getName()}\n" .
                    "Email: {$message->getEmail()}\n" .
                    "Téléphone: " . ($message->getPhone() ?: '-') . "\n\n" .
                    "Message:\n{$message->getMessage()}\n"
                );

            $mailer->send($email);

            $this->addFlash('success', 'Merci 🙌 Votre message a bien été envoyé. On vous répond rapidement.');
            return $this->redirectToRoute('contact');
        }

        return $this->render('front/pages/contact.html.twig', [
            'store' => $this->storePayload(),
            'form'  => $form->createView(),
        ]);
    }

    private function storePayload(): array
    {
        return [
            'name' => self::STORE_NAME,
            'address_lines' => self::STORE_ADDRESS_LINES,
            'phone' => self::STORE_PHONE,
            'email' => self::STORE_EMAIL,
            'maps_embed_url' => self::GOOGLE_MAPS_EMBED_URL,
        ];
    }

      #[Route('/click-collect', name: 'click_collect')]
    public function clickCollect(): Response
    {
        return $this->render('front/pages/click_collect.html.twig', [
            'pickupDays' => $this->buildPickupDays(),
            'pickupSlots' => [
                '9h00 - 10h00',
                '10h00 - 11h00',
                '11h00 - 12h00',
                '14h00 - 15h00',
                '15h00 - 16h00',
                '16h00 - 17h00',
                '17h00 - 18h00',
            ],
        ]);
    }

    private function buildPickupDays(): array
    {
        $days = [];
        $cursor = new \DateTimeImmutable('today');
        $labels = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi'];

        while (count($days) < 3) {
            if ((int) $cursor->format('N') !== 7) {
                $days[] = [
                    'iso' => $cursor->format('Y-m-d'),
                    'human' => $cursor->format('d/m'),
                    'label' => $cursor->format('Y-m-d') === (new \DateTimeImmutable('today'))->format('Y-m-d')
                        ? "Aujourd'hui"
                        : ($labels[(int) $cursor->format('N')] ?? $cursor->format('l')),
                ];
            }

            $cursor = $cursor->modify('+1 day');
        }

        return $days;
    }
}
