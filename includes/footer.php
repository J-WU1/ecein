<!-- includes/footer.php — Pied de page commun inclus en bas de chaque page -->

<footer class="bg-dark text-white mt-5 pt-4 pb-3">  <!-- fond sombre, texte blanc, marges top/bottom -->
    <div class="container">                         <!-- conteneur centré Bootstrap -->
        <div class="row">                           <!-- ligne de la grille Bootstrap pour les 3 colonnes -->
            
            <!-- Colonne 1 : Présentation -->
            <div class="col-md-4 mb-3">             <!-- colonne 1 : 4/12 sur md+, pleine largeur sur mobile -->
                <h5 class="text-warning">ECE <span class="text-white">in</span></h5> <!-- logo texte "ECE" jaune + "in" blanc -->
                <p class="small text-secondary">    <!-- texte descriptif en gris -->
                    Le réseau social professionnel de la communauté ECE Paris.
                    Connectez-vous, partagez, et développez votre carrière.
                </p>
            </div>

            <!-- Colonne 2 : Liens rapides -->
            <div class="col-md-4 mb-3">                     <!-- Liens rapides vers les pages principales -->
                <h6 class="text-warning">Liens rapides</h6> <!-- titre de la section en jaune -->
                <ul class="list-unstyled small">            <!-- liste sans puces ni indentation Bootstrap -->
                    <li><a href="index.php"        class="text-secondary text-decoration-none">Accueil</a></li> <!-- lien fil d'actualité -->
                    <li><a href="emplois.php"       class="text-secondary text-decoration-none">Offres d'emploi</a></li> <!-- lien offres d'emploi -->
                    <li><a href="notifications.php" class="text-secondary text-decoration-none">Événements</a></li> <!-- lien notifications/événements -->
                </ul>
            </div>

            <!-- Colonne 3 : Contact -->
            <div class="col-md-4 mb-3"> <!-- Coordonnées ECE Paris -->
                <h6 class="text-warning">Contact ECE Paris</h6> <!-- titre de la section en jaune -->
                <ul class="list-unstyled small text-secondary"> <!-- liste d'infos de contact sans puces -->
                    <li><i class="bi bi-geo-alt-fill me-1"></i> 10 Rue Sextius Michel, 75015 Paris</li> <!-- adresse avec icône localisation -->
                    <li><i class="bi bi-telephone-fill me-1"></i> +33 1 44 39 06 00</li> <!-- téléphone avec icône -->
                    <li><i class="bi bi-envelope-fill me-1"></i> contact@ece.fr</li> <!-- email avec icône -->
                </ul>
                <!-- carte Google Maps intégrée en iframe, loading="lazy" pour ne pas bloquer le chargement -->
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2625.7!2d2.2921!3d48.8469!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x47e6703f0!2sECE+Paris!5e0!3m2!1sfr!2sfr!4v1"
                    width="100%" height="100" style="border:0; border-radius:4px;" allowfullscreen="" loading="lazy">
                </iframe>
            </div>
        </div>

        <hr class="border-secondary"> <!-- ligne de séparation grise avant le copyright -->
        <p class="text-center text-secondary small mb-0">
            &copy; <?= date('Y') ?> ECE In — Projet Web ING2 2026. Tous droits réservés. <!-- année dynamique via PHP, pas besoin de la changer chaque année -->
        </p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> <!-- Bootstrap 5 JS : nécessaire pour les modales, collapse navbar, dropdowns -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script> <!-- jQuery 3.7 : nécessaire pour $.post, $.get, $(document).on dans main.js -->
<script src="js/main.js"></script>                                  <!-- notre JS principal : réactions, commentaires, polling, jours spéciaux -->
</body>
</html>
