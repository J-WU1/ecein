<!-- includes/footer.php — Pied de page commun à toutes les pages -->

<!-- Footer sombre avec texte blanc, marge en haut et padding vertical -->
<footer class="bg-dark text-white mt-5 pt-4 pb-3">
    <div class="container">
        <div class="row">

            <!-- Colonne 1 : Présentation du site -->
            <div class="col-md-4 mb-3">
                <!-- Logo ECE In en jaune et blanc comme dans la navbar -->
                <h5 class="text-warning">ECE <span class="text-white">in</span></h5>
                <!-- Courte description du réseau social -->
                <p class="small text-secondary">
                    Le réseau social professionnel de la communauté ECE Paris.
                    Connectez-vous, partagez, et développez votre carrière.
                </p>
            </div>

            <!-- Colonne 2 : Liens rapides vers les pages principales -->
            <div class="col-md-4 mb-3">
                <h6 class="text-warning">Liens rapides</h6>
                <!-- list-unstyled : supprime les puces de la liste -->
                <ul class="list-unstyled small">
                    <!-- text-secondary : gris clair | text-decoration-none : pas de soulignement -->
                    <li><a href="index.php"        class="text-secondary text-decoration-none">Accueil</a></li>
                    <li><a href="emplois.php"       class="text-secondary text-decoration-none">Offres d'emploi</a></li>
                    <li><a href="notifications.php" class="text-secondary text-decoration-none">Événements</a></li>
                </ul>
            </div>

        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS : nécessaire pour les composants interactifs (navbar mobile, modales...) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery : nécessaire pour les appels AJAX dans main.js -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- Notre JS personnalisé : réactions, chat, demandes d'amis... -->
<script src="js/main.js"></script>
</body>
</html>