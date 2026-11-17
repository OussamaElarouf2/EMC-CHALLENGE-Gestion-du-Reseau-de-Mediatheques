import os
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, HRFlowable, ListFlowable, ListItem
)
from reportlab.pdfgen import canvas

class NumberedCanvas(canvas.Canvas):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._saved_page_states = []

    def showPage(self):
        self._saved_page_states.append(dict(self.__dict__))
        self._startPage()

    def save(self):
        num_pages = len(self._saved_page_states)
        for state in self._saved_page_states:
            self.__dict__.update(state)
            self.draw_footer(num_pages)
            super().showPage()
        super().save()

    def draw_footer(self, page_count):
        self.saveState()
        self.setFont("Helvetica", 7.5)
        self.setFillColor(colors.HexColor("#64748B"))
        # Footer line
        self.setStrokeColor(colors.HexColor("#CBD5E1"))
        self.setLineWidth(0.5)
        self.line(36, 25, A4[0] - 36, 25)
        
        self.drawString(36, 15, "Système de Gestion de Médiathèques — EMC PFE 2026 — Sujet 4")
        self.drawRightString(A4[0] - 36, 15, f"Note de Cadrage Conceptuelle — Page {self._pageNumber} sur {page_count} (85 tests validés)")
        self.restoreState()

def build_pdf(filename="note-de-cadrage.pdf"):
    # Margins: 36pt = 0.5 inch = 12.7mm
    doc = SimpleDocTemplate(
        filename,
        pagesize=A4,
        leftMargin=36,
        rightMargin=36,
        topMargin=30,
        bottomMargin=36
    )

    styles = getSampleStyleSheet()

    # Custom styles
    title_style = ParagraphStyle(
        'DocTitle',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=13,
        leading=15,
        textColor=colors.HexColor('#0F172A')
    )
    
    subtitle_style = ParagraphStyle(
        'DocSubTitle',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=8.5,
        leading=10,
        textColor=colors.HexColor('#2563EB')
    )

    meta_style = ParagraphStyle(
        'DocMeta',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=7.5,
        leading=9.5,
        textColor=colors.HexColor('#475569'),
        alignment=2 # Right align
    )

    heading_style = ParagraphStyle(
        'SectionHeading',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=8.5,
        leading=10,
        textColor=colors.HexColor('#1E40AF'),
        spaceBefore=4,
        spaceAfter=2
    )

    body_style = ParagraphStyle(
        'Body',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=7.5,
        leading=9.2,
        textColor=colors.HexColor('#1E293B'),
        alignment=4 # Justify
    )

    bullet_style = ParagraphStyle(
        'BulletText',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=7.2,
        leading=8.8,
        textColor=colors.HexColor('#1E293B'),
        leftIndent=8,
        spaceBefore=1,
        spaceAfter=1
    )

    story = []

    # Header Table
    header_left = [
        Paragraph("Système de Gestion de Médiathèques", title_style),
        Spacer(1, 2),
        Paragraph("Note de Cadrage Conceptuelle & Synthèse d'Architecture — Sujet 4", subtitle_style)
    ]
    header_right = [
        Paragraph("<b>Challenge PFE EMC 2026</b>", meta_style),
        Paragraph("Candidat : <b>Oussama El Arouf</b>", meta_style),
        Paragraph("Stack : Symfony 7.4 / PHP 8.3.36 / MySQL 8", meta_style)
    ]
    
    header_table = Table([[header_left, header_right]], colWidths=[330, 192])
    header_table.setStyle(TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING', (0, 0), (-1, -1), 0),
        ('RIGHTPADDING', (0, 0), (-1, -1), 0),
        ('TOPPADDING', (0, 0), (-1, -1), 0),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 0),
    ]))
    story.append(header_table)
    story.append(Spacer(1, 3))
    story.append(HRFlowable(width="100%", thickness=1.5, color=colors.HexColor("#2563EB"), spaceBefore=2, spaceAfter=4))

    # Section 1
    story.append(Paragraph("1. Choix du Sujet & Motivation", heading_style))
    story.append(Paragraph(
        "Le <b>Sujet 4 (Réseau de médiathèques)</b> a été choisi pour son haut niveau d'exigence technique et sa richesse métier. "
        "Au-delà d'un catalogue standard, la gestion multi-sites soulève des défis d'ingénierie majeurs : modélisation bibliographique fine "
        "(norme FRBR), logistique inter-sites, intégrité sous fortes concurrences de réservation (file FIFO) et interopérabilité "
        "avec un service public tiers (Open Library) sous contrainte de débit.",
        body_style
    ))

    # Section 2
    story.append(Paragraph("2. Décomposition du Besoin & Protocole Méthodologique", heading_style))
    story.append(Paragraph(
        "Le projet a été mené selon un découpage progressif en 5 niveaux, encadré par le protocole en 5 temps "
        "<i>(Planifier &rarr; Valider &rarr; Exécuter &rarr; Vérifier &rarr; Commiter)</i> :",
        body_style
    ))
    story.append(Paragraph("• <b>Niveau 1 (Modèle & Back-Office)</b> : 13 entités, migrations Doctrine, fixtures réalistes (~300 exemplaires) et administration EasyAdmin 4.", bullet_style))
    story.append(Paragraph("• <b>Niveau 2 (Cœur Métier & Concurrence)</b> : Services purs (<code>LoanService</code>, <code>ReservationQueueService</code>, <code>TransferService</code>, <code>PenaltyCalculator</code>) et 29 tests.", bullet_style))
    story.append(Paragraph("• <b>Niveau 3 (Expérience Usager & Métriques)</b> : Auth 3 rôles hiérarchiques, catalogue public réactif, espace adhérent avec Voters, guichet express et taux de rotation.", bullet_style))
    story.append(Paragraph("• <b>Niveau 4 (Interopérabilité Open Library)</b> : Client HTTP identifié (User-Agent officiel), limitation 3 req/s, préservation des données locales et import CLI.", bullet_style))
    story.append(Paragraph("• <b>Niveau 5 (Livraison Clé en Main)</b> : Docker Compose en 1 commande (<code>docker compose up --build -d</code>) avec amorçage automatique des migrations et données.", bullet_style))

    # Section 3
    story.append(Paragraph("3. Choix d'Architecture Majeurs & Compromis Réalisés", heading_style))
    story.append(Paragraph(
        "• <b>Modélisation FRBR (<code>Work</code> &rarr; <code>Edition</code> &rarr; <code>Copy</code>)</b> : "
        "Séparation de l'œuvre intellectuelle et de ses publications concrètes (formats <code>BOOK</code>, <code>COMIC</code>, <code>DVD</code>). "
        "Les contributeurs multiples (auteurs, illustrateurs, traducteurs) sont articulés via la table pivot <code>EditionContributor</code> avec rôle typé et position d'affichage.",
        bullet_style
    ))
    story.append(Paragraph(
        "• <b>Concurrence sous MySQL 8 (<code>REPEATABLE READ</code>)</b> : "
        "Double protection contre les écritures concurrentes : (1) Verrouillage pessimiste ordonné déterministe (Member puis Copy) éliminant tout risque d'interblocage (<i>deadlock</i>) ; "
        "(2) Colonnes d'unicité nullables (<code>activeCopyId</code> et <code>activeMemberEditionKey</code>). "
        "InnoDB autorisant une infinité de <code>NULL</code>, les emprunts passés coexistent sans conflit, tandis que les doublons concurrents sont physiquement rejetés.",
        bullet_style
    ))
    story.append(Paragraph(
        "• <b>Logistique Inter-sites & Statut Pivot <code>AWAITING_TRANSFER</code></b> : "
        "La réservation d'un document sur l'autre site assigne l'exemplaire (<code>IN_TRANSIT</code>) et passe immédiatement la réservation à <code>AWAITING_TRANSFER</code>. "
        "Elle quitte ainsi la file FIFO <code>WAITING</code>, interdisant mathématiquement toute double allocation lors d'un retour simultané. Le délai de retrait de 5 jours calendaires "
        "débute strictement lors de la réception physique au site de retrait (<code>READY</code>).",
        bullet_style
    ))
    story.append(Paragraph(
        "• <b>Durcissement de Sécurité PHP 8.3 (PHP2Shell)</b> : "
        "En réponse aux failles critiques CVE-2026-109618 / CVE-2026-109619 et à la fin de vie imminente de PHP 8.2, le runtime hôte et conteneurisé a été verrouillé sur PHP 8.3.36 "
        "avec désactivation de <code>session.upload_progress.enabled</code>.",
        bullet_style
    ))

    # Section 4
    story.append(Paragraph("4. Rôle & Supervision Critique des Outils d'IA", heading_style))
    story.append(Paragraph(
        "• <b>Apports à forte valeur ajoutée</b> : Génération rapide des squelettes d'entités, mise en page Bootstrap 5 / Twig, constitution des jeux de données de démonstration via Faker, et formalisation des scénarios de test nominaux et aux limites.",
        bullet_style
    ))
    story.append(Paragraph(
        "• <b>Contrôle et arbitrage de l'ingénieur</b> : Correction de choix inadaptés de l'IA (logique de réservation risquant d'allouer deux fois un livre en transit, décompte prématuré des 5 jours avant l'arrivée du colis ou inversions de paramètres). "
        "La couverture exhaustive de <b>85 tests automatisés (382 assertions passantes à 100%)</b> a assuré une validation continue sans faille.",
        bullet_style
    ))

    # Section 5
    story.append(Paragraph("5. Perspectives d'Évolution (Avec une Semaine Supplémentaire)", heading_style))
    story.append(Paragraph(
        "• <b>Notifications Événementielles (Email / SMS)</b> : Intégration de <code>symfony/mailer</code> et webhooks SMS pour notifier immédiatement l'arrivée d'un document réservé et relancer l'adhérent à J-1 avant l'expiration des 5 jours.",
        bullet_style
    ))
    story.append(Paragraph(
        "• <b>API pour Bornes & Kiosques RFID</b> : Exposition d'endpoints REST sécurisés (API Platform / OAuth2) pour automatiser emprunts et retours sur bornes physiques.",
        bullet_style
    ))
    story.append(Paragraph(
        "• <b>Gestion Avancée des Navettes Inter-sites</b> : Module de groupage avec génération de bordereaux de colisage, plannings de passage et signature numérique.",
        bullet_style
    ))

    doc.build(story, canvasmaker=NumberedCanvas)
    print(f"PDF built successfully: {filename}")

if __name__ == '__main__':
    build_pdf()
