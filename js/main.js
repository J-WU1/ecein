// js/main.js — JavaScript principal ECE In
// Utilise jQuery chargé dans footer.php juste avant ce fichier

$(document).ready(function () { // attend que tout le DOM soit chargé avant d'exécuter le code

    // Réactions sur les posts : like, love, bravo
    // $(document).on au lieu de $('.reaction-btn').on pour capturer aussi les boutons ajoutés dynamiquement
    $(document).on('click', '.reaction-btn', function () {
        const btn    = $(this);             // référence au bouton cliqué
        const postId = btn.data('post-id'); // lit l'attribut data-post-id du bouton HTML
        const type   = btn.data('type');    // lit l'attribut data-type : 'like', 'love' ou 'bravo'

        $.post('api/posts.php', { action: 'react', post_id: postId, type: type }, function (res) { // envoi AJAX POST vers api/posts.php
            if (res.success) {                                  // si le serveur confirme que la réaction a bien été enregistrée
                btn.find('.reaction-count').text(res.count);    // met à jour le compteur affiché dans le bouton
                btn.toggleClass('active', res.reacted);         // ajoute la classe 'active' si réagi, la retire si déjà réagi (toggle)
            }
        }, 'json'); // indique à jQuery que la réponse attendue est du JSON
    });

    // Ajout d'un commentaire sans rechargement de page
    $(document).on('submit', '.comment-form', function (e) {    // intercepte la soumission de n'importe quel formulaire .comment-form
        e.preventDefault();                                     // bloque la soumission classique qui rechargerait la page
        const form   = $(this);                                 // référence au formulaire soumis
        const postId = form.data('post-id');                    // lit l'ID du post depuis l'attribut data-post-id du formulaire
        const input  = form.find('input[name="comment"]');      // récupère le champ texte du commentaire
        const text   = input.val().trim();                      // lit la valeur saisie et supprime les espaces en début/fin

        if (!text) return; // on n'envoie rien si le champ est vide

        $.post('api/posts.php', { action: 'comment', post_id: postId, contenu: text }, function (res) { // envoi AJAX POST
            if (res.success) { // si le commentaire a bien été enregistré en base
                const html = `<div class="d-flex gap-2 mt-2"> <!-- construit le HTML du nouveau commentaire avec les données renvoyées par l'API -->
                    <img src="uploads/${res.photo}" class="rounded-circle" width="32" height="32" style="object-fit:cover;"> <!-- photo de l'auteur du commentaire -->
                    <div class="bg-light rounded p-2 flex-grow-1">
                        <strong>${res.prenom} ${res.nom}</strong> <!-- nom de l'auteur -->
                        <p class="mb-0 small">${res.contenu}</p> <!-- texte du commentaire -->
                    </div>
                </div>`;
                form.closest('.post-comments').find('.comments-list').append(html); // insère le nouveau commentaire à la fin de la liste
                input.val(''); // vide le champ texte après envoi réussi
            }
        }, 'json');
    });

    // Afficher ou masquer la section commentaires d'un post
    $(document).on('click', '.toggle-comments', function () {   // écoute tous les éléments .toggle-comments
        const postId  = $(this).data('post-id');                // récupère l'ID du post concerné
        const section = $(`#comments-${postId}`);               // cible la div #comments-{id} de ce post
        section.toggleClass('d-none');                          // alterne entre visible (sans d-none) et masqué (avec d-none)
    });

    // Messagerie instantanée : envoi et réception de messages en temps réel
    let lastMessageId = 0; // mémorise l'ID du dernier message reçu pour ne pas re-afficher les anciens

    $('#send-message-form').on('submit', function (e) {      // écoute la soumission du formulaire d'envoi de message
        e.preventDefault();                                  // bloque le rechargement de la page
        const receiverId = $('#receiver-id').val();          // ID de l'interlocuteur, stocké dans un input caché
        const contenu    = $('#message-input').val().trim(); // texte du message saisi

        if (!contenu) return; // on n'envoie rien si le champ est vide

        $.post('api/messages.php', { action: 'send', receiver_id: receiverId, contenu: contenu }, function (res) { // envoi AJAX POST du message
            if (res.success) { // si le message a bien été enregistré
                appendMessage(res.message, true); // affiche le message dans la boîte de chat, true = envoyé par moi (aligné à droite)
                $('#message-input').val(''); // vide le champ après envoi
                lastMessageId = res.message.id; // met à jour le dernier ID connu
                scrollChatToBottom(); // fait défiler vers le bas pour voir le nouveau message
            }
        }, 'json');
    });

    // Polling : récupère les nouveaux messages toutes les 3 secondes sans recharger la page
    function pollMessages() {       // vérifie s'il y a de nouveaux messages depuis le dernier poll
        const receiverId = $('#receiver-id').val(); // ID de l'interlocuteur courant
        if (!receiverId) return;    // on ne poll que si on est dans une conversation ouverte

        $.get('api/messages.php', { // envoi AJAX GET pour récupérer les nouveaux messages
            action: 'poll',
            receiver_id: receiverId,
            last_id: lastMessageId  // l'API ne renvoie que les messages plus récents que cet ID
        }, function (res) {
            if (res.messages && res.messages.length > 0) {  // si de nouveaux messages sont arrivés
                res.messages.forEach(function (msg) {       // on parcourt chaque nouveau message
                    appendMessage(msg, msg.is_mine);        // l'affiche, is_mine = true si je l'ai envoyé
                    lastMessageId = msg.id;                 // met à jour le dernier ID après chaque message
                });
                scrollChatToBottom(); // fait défiler en bas après ajout des nouveaux messages
            }
        }, 'json');
    }

    if ($('#chat-box').length) {            // vérifie que la boîte de chat existe sur la page actuelle
        pollMessages();                     // charge les messages existants dès l'ouverture de la messagerie
        setInterval(pollMessages, 3000);    // relance le polling toutes les 3 secondes automatiquement
    }

    function appendMessage(msg, isMine) {   // crée et insère une bulle de message dans le chat
        const cssClass = isMine ? 'msg-sent' : 'msg-received'; // classe CSS selon l'expéditeur
        const html = `<div class="${cssClass}">             <!-- conteneur de la bulle -->
            <span>${escapeHtml(msg.contenu)}</span>         <!-- texte du message échappé contre le XSS -->
            <div class="msg-time">${msg.created_at}</div>   <!-- heure du message -->
        </div>`;
        $('#chat-box').append(html);        // ajoute la bulle à la fin de la boîte de chat
    }

    function scrollChatToBottom() {                      // fait défiler la boîte de chat jusqu'au dernier message
        const box = document.getElementById('chat-box'); // récupère l'élément DOM natif
        if (box) box.scrollTop = box.scrollHeight;       // scrollHeight = hauteur totale du contenu, scrollTop y positionne la vue
    }

    // Prévisualisation d'image ou vidéo avant upload
    $('#post-media-input').on('change', function () {    // déclenché quand l'utilisateur sélectionne un fichier
        const file = this.files[0];                      // récupère le premier fichier sélectionné
        if (!file) return;                               // rien à prévisualiser si aucun fichier

        const reader = new FileReader();                 // API navigateur pour lire un fichier localement sans upload
        reader.onload = function (e) {                   // déclenché quand la lecture est terminée
            if (file.type.startsWith('image/')) {        // si c'est une image
                $('#media-preview').html(`<img src="${e.target.result}" class="img-fluid rounded mt-2" style="max-height:200px;">`); // affiche l'aperçu image
            } else if (file.type.startsWith('video/')) { // si c'est une vidéo
                $('#media-preview').html(`<video src="${e.target.result}" controls class="w-100 rounded mt-2" style="max-height:200px;"></video>`); // affiche le lecteur vidéo
            }
        };
        reader.readAsDataURL(file); // lit le fichier et le convertit en base64 pour l'affichage local
    });

    // Demande d'Ami : bouton dans la page réseau
    $(document).on('click', '.add-friend-btn', function () {
        const btn    = $(this);             // référence au bouton cliqué
        const userId = btn.data('user-id'); // ID de la personne à qui envoyer la demande

        $.post('api/friends.php', { action: 'request', friend_id: userId }, function (res) { // envoi AJAX POST
            if (res.success) {              // si la demande a bien été enregistrée
                btn.text('Demande envoyée').addClass('btn-secondary').removeClass('btn-ece').prop('disabled', true); // désactive le bouton pour éviter un double-envoi
            }
        }, 'json');
    });

    // Accepter une demande d'ami reçue
    $(document).on('click', '.accept-friend-btn', function () {
        const btn    = $(this);
        const userId = btn.data('user-id'); // ID de la personne qui a envoyé la demande

        $.post('api/friends.php', { action: 'accept', friend_id: userId }, function (res) { // met le statut à 'accepted' en base
            if (res.success) {
                btn.closest('.friendship-request').fadeOut(); // cache la carte de demande avec une animation de fondu
            }
        }, 'json');
    });

    // Refuser une demande d'ami reçue
    $(document).on('click', '.reject-friend-btn', function () {
        const btn    = $(this);
        const userId = btn.data('user-id'); // ID de la personne dont on refuse la demande

        $.post('api/friends.php', { action: 'reject', friend_id: userId }, function (res) { // supprime la ligne dans friendships
            if (res.success) {
                btn.closest('.friendship-request').fadeOut(); // cache la carte avec animation
            }
        }, 'json');
    });

    // Sécurité 
    function escapeHtml(text) {              // échappe les caractères HTML dangereux pour protéger contre le XSS
        return $('<div>').text(text).html(); // jQuery encode automatiquement < > & " ' via .text()
    }

    // Jours Spéciaux : affiche une bannière festive selon la date du jour
    const today   = new Date();             // date et heure actuelles du navigateur
    const month   = today.getMonth() + 1;   // getMonth() retourne 0-11, on ajoute 1 pour avoir 1-12
    const day     = today.getDate();        // jour du mois entre 1 et 31
    let specialMsg = null;                  // contiendra le message festif si on est un jour spécial

    if (month === 2 && day === 14)       specialMsg = '💕 Bonne Saint-Valentin à toute la communauté ECE !';          // 14 février
    else if (month === 12 && day >= 24)  specialMsg = '🎄 Joyeux Noël depuis ECE In !';                               // du 24 au 31 décembre
    else if (month === 5 && day >= 25 && day <= 31 && today.getDay() === 0) specialMsg = '💐 Bonne Fête des Mères !'; // dernier dimanche de mai, getDay()===0 signifie dimanche
    else if (month === 6 && day >= 15 && day <= 21 && today.getDay() === 0) specialMsg = '👨‍👧 Bonne Fête des Pères !';  // 3e dimanche de juin entre le 15 et le 21
    else if (month === 1 && day === 1)   specialMsg = '🎆 Bonne Année à toute la communauté ECE !';                   // 1er janvier

    if (specialMsg && $('#special-day-banner').length) {                 // vérifie qu'on est un jour spécial ET que l'élément bannière existe sur la page
        $('#special-day-banner').text(specialMsg).removeClass('d-none'); // insère le message et rend la bannière visible
    }

}); // fin de $(document).ready
