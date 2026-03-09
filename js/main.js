/*  js/main.js — JavaScript principal ECE In
    Utilise jQuery (chargé dans footer.php) */

$(document).ready(function () {

    /* 1. RÉACTIONS SUR LES POSTS (like / love / bravo) */

    $(document).on('click', '.reaction-btn', function () {
        const btn    = $(this);
        const postId = btn.data('post-id');
        const type   = btn.data('type');
        $.post('api/posts.php', { action: 'react', post_id: postId, type: type }, function (res) {
            if (res.success) {
                btn.find('.reaction-count').text(res.count);
                btn.toggleClass('active', res.reacted);
            }
        }, 'json');
    });

    /* 2. AJOUT D'UN COMMENTAIRE */

    $(document).on('submit', '.comment-form', function (e) {
        e.preventDefault();
        const form   = $(this);
        const postId = form.data('post-id');
        const input  = form.find('input[name="comment"]');
        const text   = input.val().trim();
        if (!text) return;
        $.post('api/posts.php', { action: 'comment', post_id: postId, contenu: text }, function (res) {
            if (res.success) {
                const html = `<div class="d-flex gap-2 mt-2">
                    <img src="uploads/${res.photo}" class="rounded-circle" width="32" height="32" style="object-fit:cover;">
                    <div class="bg-light rounded p-2 flex-grow-1">
                        <strong>${res.prenom} ${res.nom}</strong>
                        <p class="mb-0 small">${res.contenu}</p>
                    </div>
                </div>`;
                form.closest('.post-comments').find('.comments-list').append(html);
                input.val('');
            }
        }, 'json');
    });

    /* 3. AFFICHER / MASQUER les commentaires */
    
    $(document).on('click', '.toggle-comments', function () {
        const postId  = $(this).data('post-id');
        const section = $(`#comments-${postId}`);
        section.toggleClass('d-none');
    });

});