<?php
defined('ABSPATH') || exit;
/** Community moderation and in-app announcements. No changes to orders or affiliate. */
final class ARC_Management {
    public static function boot(): void {
        add_action('init',[self::class,'post_type']);
        add_action('admin_menu',[self::class,'menu'],20);
        add_action('admin_post_arc_manage_member',[self::class,'save_member']);
        add_action('admin_post_arc_delete_test_member',[self::class,'delete_test_member']);
        add_action('admin_post_arc_manage_friend',[self::class,'manage_friend']);
        add_action('wp_ajax_arc_member_lookup',[self::class,'member_lookup']);
        add_action('admin_post_arc_send_broadcast',[self::class,'send_broadcast']);
        add_action('arc_deliver_broadcast',[self::class,'deliver_broadcast'],10,1);
    }
    public static function post_type(): void {
        register_post_type('arc_broadcast',['label'=>'Oznámení AR','public'=>false,'show_ui'=>false,'supports'=>['title','editor','author']]);
    }
    public static function menu(): void {
        add_submenu_page('ar-community','Členové komunity','Členové komunity','manage_options','arc-members',[self::class,'members']);
        add_submenu_page('ar-community','Oznámení','Oznámení','manage_options','arc-messages',[self::class,'messages']);
    }
    private static function denied(): void {
        if(!current_user_can('manage_options'))wp_die('Nemáš oprávnění.',403);
    }
    public static function members(): void {
        self::denied();
        echo '<div class="wrap"><h1>AR Community – členové sítě</h1><p>Správa veřejné prezentace a komunitních oprávnění. Blokace komunity nemaže účet ani nezastavuje WooCommerce objednávky či affiliate provize.</p>';
        if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>Změny profilu byly uloženy.</p></div>';
        if(isset($_GET['friend_updated']))echo '<div class="notice notice-success"><p>Přátelství bylo upraveno. Affiliate vazby se nezměnily.</p></div>';
        if(isset($_GET['member_deleted']))echo '<div class="notice notice-success"><p>Testovací účet byl odebrán. Komunitní profil a aktivní affiliate vazby byly vyčištěny. Účetní historie zůstává zachována.</p></div>';
        $selected=absint($_GET['user_id']??0);
        if($selected){
            $u=get_userdata($selected);
            if($u){
                $alias=(string)get_user_meta($selected,'_arc_profile_alias',true);
                $bio=(string)get_user_meta($selected,ARC_Data::META_BIO,true);
                $interests=(string)get_user_meta($selected,ARC_Data::META_INTERESTS,true);
                $visibility=ARC_Profile::visibility($selected);
                $avatar=(int)get_user_meta($selected,'_arc_avatar_attachment_id',true);
                echo '<h2>Úprava člena #'.(int)$selected.' – '.esc_html($u->display_name).'</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                echo '<input type="hidden" name="action" value="arc_manage_member"><input type="hidden" name="user_id" value="'.(int)$selected.'">';
                wp_nonce_field('arc_manage_member_'.$selected);
                echo '<table class="form-table"><tr><th>Přezdívka</th><td><input class="regular-text" maxlength="65" name="alias" value="'.esc_attr($alias).'"></td></tr>';
                echo '<tr><th>O mně (odkazy, tučné písmo)</th><td><textarea rows="6" class="large-text" name="bio">'.esc_textarea($bio).'</textarea></td></tr>';
                echo '<tr><th>Aktivity</th><td><input class="regular-text" maxlength="250" name="interests" value="'.esc_attr($interests).'"></td></tr>';
                echo '<tr><th>Viditelnost profilu</th><td><select name="visibility">';
                foreach(['private'=>'Neveřejný','network'=>'Jen potvrzení přátelé','public'=>'Veřejný'] as $value=>$label)echo '<option value="'.esc_attr($value).'" '.selected($visibility,$value,false).'>'.esc_html($label).'</option>';
                echo '</select><p class="description">Neveřejné profily bez výslovného podkladu nezveřejňuj.</p></td></tr>';
                echo '<tr><th>ID fotografie ve WordPress médiích</th><td><input type="number" min="0" name="avatar_id" value="'.$avatar.'"><p class="description">Uveď ID obrázku z knihovny médií (0 = odebrat).</p></td></tr>';
                echo '<tr><th>Komunitní blokace</th><td><label><input type="checkbox" name="suspended" value="1" '.checked((bool)get_user_meta($selected,'_arc_community_suspended',true),true,false).'> Zakázat nové příspěvky, reakce, pozvánky a komunitní úpravy</label><p class="description">Čtení veřejného obsahu a obchodní funkce zůstávají dostupné.</p></td></tr></table>';
                submit_button('Uložit úpravy člena');echo '</form>';
                echo self::delete_member_form($selected);echo '<hr>';
            }
        }
        $q=sanitize_text_field((string)wp_unslash($_GET['s']??''));$page=max(1,absint($_GET['paged']??1));
        echo '<form method="get"><input type="hidden" name="page" value="arc-members"><input type="search" name="s" placeholder="Jméno nebo e-mail" value="'.esc_attr($q).'"> <button class="button">Hledat</button></form>';
        $args=['number'=>30,'paged'=>$page,'orderby'=>'ID','order'=>'DESC'];
        if($q!=='')$args['search']='*'.$q.'*';
        $users=new WP_User_Query($args);
        echo '<table class="widefat striped" style="max-width:1250px"><thead><tr><th>WP ID</th><th>Člen</th><th>E-mail (jen admin)</th><th>Viditelnost</th><th>Stav</th><th>App / Zařízení</th><th>Rychlá správa</th></tr></thead><tbody>';
        foreach($users->get_results() as $u){
            $id=(int)$u->ID;
            echo '<tr><td>'.(int)$id.'</td><td>'.esc_html($u->display_name).'</td><td>'.esc_html($u->user_email).'</td><td>'.esc_html(ARC_Profile::visibility($id)).'</td><td>'.(get_user_meta($id,'_arc_community_suspended',true)?'Pozastaven':'Aktivní').'</td><td>'.(class_exists('ARC_PWA_Devices')?ARC_PWA_Devices::admin_cell($id):'Nezjištěno').'</td><td><details class="arc-admin-member" id="arc-admin-member-'.$id.'"><summary class="button">Rozbalit správu</summary>';
            echo self::member_quick_form($id);
            echo '</details></td></tr>';
        }
        echo '</tbody></table><p>Počet nalezených účtů: '.(int)$users->get_total().'.</p>';
        if($page>1)echo '<a class="button" href="'.esc_url(add_query_arg(['page'=>'arc-members','s'=>$q,'paged'=>$page-1],admin_url('admin.php'))).'">← Předchozí</a> ';
        if($page*30<(int)$users->get_total())echo '<a class="button" href="'.esc_url(add_query_arg(['page'=>'arc-members','s'=>$q,'paged'=>$page+1],admin_url('admin.php'))).'">Další →</a>';
        echo '<style>.arc-admin-member summary{cursor:pointer;white-space:nowrap}.arc-admin-member[open]{min-width:340px}.arc-admin-quick{padding:15px;background:#fafafa;border:1px solid #ddd;border-radius:8px;margin-top:8px;max-width:450px}.arc-admin-quick label{display:block;margin:9px 0}.arc-admin-quick input[type=text],.arc-admin-quick input[type=search],.arc-admin-quick textarea,.arc-admin-quick select{max-width:100%;width:100%}.arc-admin-quick .arc-friend-name{display:inline-block;margin:3px 8px 3px 0}.arc-admin-quick button{cursor:pointer}.arc-admin-options{position:relative}.arc-admin-results{max-height:170px;overflow:auto;border:1px solid #ddd;background:#fff}.arc-admin-results button{display:block;width:100%;text-align:left;padding:7px;background:#fff;border:0;border-bottom:1px solid #eee}</style>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){if(location.hash.indexOf("#arc-admin-member-")===0){var el=document.querySelector(location.hash);if(el)el.open=true}});document.addEventListener("input",function(e){var input=e.target.closest("[data-arc-admin-search]");if(!input)return;clearTimeout(input._timer);var box=input.parentNode.querySelector("[data-arc-admin-results]");var hidden=input.closest("form").querySelector("[name=friend_id]");hidden.value="";if(input.value.trim().length<2){box.innerHTML="";return;}input._timer=setTimeout(function(){var fd=new FormData();fd.set("action","arc_member_lookup");fd.set("nonce",'.wp_json_encode(wp_create_nonce('arc_member_lookup')).');fd.set("q",input.value.trim());fd.set("exclude",input.dataset.exclude||"0");fetch(ajaxurl,{method:"POST",credentials:"same-origin",body:fd}).then(r=>r.json()).then(data=>{box.replaceChildren();(data.data||[]).forEach(u=>{var b=document.createElement("button");b.type="button";b.textContent=u.name+" (#"+u.id+")";b.onclick=()=>{input.value=u.name;hidden.value=String(u.id);box.replaceChildren()};box.append(b)})}).catch(()=>{box.textContent="Vyhledávání není dostupné."})},270)});</script></div>';
    }
    /** Only existing WP users; never expose these results to the public REST API. */
    public static function member_lookup(): void {
        if(!current_user_can('edit_users'))wp_send_json_error(['message'=>'Oprávnění chybí.'],403);
        check_ajax_referer('arc_member_lookup','nonce');
        $q=mb_substr(sanitize_text_field((string)wp_unslash($_POST['q']??'')),0,65);
        $exclude=absint($_POST['exclude']??0);
        if(mb_strlen($q)<2)wp_send_json_success([]);
        $users=get_users(['search'=>'*'.$q.'*','search_columns'=>['user_login','user_email','display_name'],'number'=>12,'fields'=>['ID','display_name']]);
        $result=[];
        foreach($users as $u){if((int)$u->ID!==$exclude)$result[]=['id'=>(int)$u->ID,'name'=>(string)$u->display_name];}
        wp_send_json_success($result);
    }
    private static function member_quick_form(int $id): string {
        $u=get_userdata($id);if(!$u || !current_user_can('edit_user',$id))return '';
        $bio=(string)get_user_meta($id,ARC_Data::META_BIO,true);
        $alias=(string)get_user_meta($id,'_arc_profile_alias',true);
        $interests=(string)get_user_meta($id,ARC_Data::META_INTERESTS,true);
        $visibility=ARC_Profile::visibility($id);
        ob_start();
        echo '<div class="arc-admin-quick"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="arc_manage_member"><input type="hidden" name="user_id" value="'.$id.'">';
        wp_nonce_field('arc_manage_member_'.$id);
        echo '<h4>Upravit komunitní profil</h4><label>Zobrazované jméno (WP)<input type="text" maxlength="65" name="alias" value="'.esc_attr($alias?:$u->display_name).'" required></label>';
        echo '<label>O mně<textarea name="bio" rows="3">'.esc_textarea($bio).'</textarea></label>';
        echo '<label>Sporty / zájmy<input type="text" name="interests" maxlength="250" value="'.esc_attr($interests).'"></label>';
        echo '<label>Viditelnost profilu<select name="visibility">';
        foreach(['private'=>'Soukromý','network'=>'Přátelé','public'=>'Veřejný'] as $v=>$name)echo '<option value="'.esc_attr($v).'" '.selected($v,$visibility,false).'>'.esc_html($name).'</option>';
        echo '</select></label><input type="hidden" name="avatar_id" value="'.absint(get_user_meta($id,'_arc_avatar_attachment_id',true)).'">';
        echo '<label><input type="checkbox" name="suspended" value="1" '.checked((string)get_user_meta($id,'_arc_community_suspended',true),'1',false).'> Pozastavit komunitu</label>';
        echo '<button type="submit" class="button button-primary">Uložit profil</button></form><hr>';
        echo '<h4>Správa přátel (pouze sociální síť)</h4>';
        $friendIds=ARC_Relations::connected_ids($id);
        if(!$friendIds)echo '<p>Člen zatím nemá přátele.</p>';
        foreach(array_slice($friendIds,0,100) as $fid){
            if(!get_userdata($fid))continue;
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="arc-friend-name">';
            echo '<input type="hidden" name="action" value="arc_manage_friend"><input type="hidden" name="user_id" value="'.$id.'"><input type="hidden" name="friend_id" value="'.absint($fid).'"><input type="hidden" name="mode" value="remove">';
            wp_nonce_field('arc_manage_friend_'.$id);
            echo '<span>'.esc_html(get_userdata($fid)->display_name).'</span> <button class="button button-small" type="submit" onclick="return confirm(\'Odebrat přátelství?\')" aria-label="Odebrat přátelství">×</button></form>';
        }
        echo '<form class="arc-admin-options" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="arc_manage_friend"><input type="hidden" name="user_id" value="'.$id.'"><input type="hidden" name="friend_id" value=""><input type="hidden" name="mode" value="add">';
        wp_nonce_field('arc_manage_friend_'.$id);
        echo '<label>Najít dalšího člena podle jména nebo e-mailu<input type="search" data-arc-admin-search data-exclude="'.$id.'" autocomplete="off" placeholder="Začni psát jméno…"></label><div class="arc-admin-results" data-arc-admin-results></div><p><button class="button" type="submit">Přidat jako přítele</button></p>';
        echo '</form><p class="description">Správa přátel nemění přiřazení Fresh Affiliate ani provize.</p>';
        echo self::delete_member_form($id);
        echo '</div>';
        return (string)ob_get_clean();
    }
    public static function manage_friend(): void {
        if(!current_user_can('edit_users'))wp_die('Nedostatečná oprávnění.',403);
        $id=absint($_POST['user_id']??0);$fid=absint($_POST['friend_id']??0);
        check_admin_referer('arc_manage_friend_'.$id);
        if(!$id||!$fid||$id===$fid||!get_userdata($id)||!get_userdata($fid)||!current_user_can('edit_user',$id)||!current_user_can('edit_user',$fid))wp_die('Neplatní členové.',400);
        $mode=sanitize_key((string)wp_unslash($_POST['mode']??''));
        global $wpdb;$t=ARC_Relations::table();
        if($mode==='add'){
            // Canonical single accepted edge, never two duplicate edges or orphan requests.
            if(!ARC_Relations::connected($id,$fid)){
                $wpdb->query($wpdb->prepare("DELETE FROM $t WHERE kind='connection' AND ((user_id=%d AND target_id=%d) OR (user_id=%d AND target_id=%d))",$id,$fid,$fid,$id));
                $ok=$wpdb->insert($t,['user_id'=>$id,'target_id'=>$fid,'kind'=>'connection','status'=>'accepted','created_at'=>current_time('mysql',true)],['%d','%d','%s','%s','%s']);
                if(!$ok)wp_die('Přátelství nelze uložit.',500);
            }
        } elseif($mode==='remove') {
            $wpdb->query($wpdb->prepare("DELETE FROM $t WHERE kind='connection' AND ((user_id=%d AND target_id=%d) OR (user_id=%d AND target_id=%d))",$id,$fid,$fid,$id));
        } else wp_die('Neplatná akce.',400);
        wp_safe_redirect(add_query_arg(['page'=>'arc-members','s'=>'','friend_updated'=>1],admin_url('admin.php')).'#arc-admin-member-'.$id);exit;
    }
    /** Dangerous operation: only an administrator can permanently delete a non-staff TEST account. */
    private static function can_remove_member(int $uid): bool {
        $user=get_userdata($uid);
        if (!$user || $uid===get_current_user_id() || !current_user_can('manage_options')
            || !current_user_can('delete_users') || !current_user_can('delete_user',$uid)
            || is_multisite() || is_super_admin($uid)) return false;
        if (array_intersect(['administrator','shop_manager','editor'],(array)$user->roles)) return false;
        // Do not reassign published editorial articles, products or pages as a side effect.
        global $wpdb;
        $edited=(int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author=%d AND post_type IN ('post','page','product','product_variation') AND post_status NOT IN ('auto-draft','trash')",$uid));
        return $edited===0;
    }
    private static function delete_member_form(int $uid): string {
        if (!self::can_remove_member($uid)) return '';
        $user=get_userdata($uid);
        if (!$user) return '';
        $stats=class_exists('Fresh_Affiliate_User_Cleanup') ? Fresh_Affiliate_User_Cleanup::preview($uid) : null;
        ob_start();
        echo '<div class="arc-admin-delete-member" style="border:1px solid #cb5b5b;padding:14px;margin:16px 0;border-radius:8px;background:#fff6f6">';
        echo '<h4 style="margin-top:0">Odstranit testovací účet (nevratné)</h4>';
        echo '<p>Smaže celý WordPress/Woo zákaznický účet, komunitní profil, příspěvky, přátele, oznámení a aktivní přiřazení lifetime partnera. Stejný e-mail pak půjde znovu registrovat.</p>';
        if ($stats) {
            echo '<p>Affiliate: přiřazení zákazníka <strong>'.(int)$stats['incoming'].'</strong>, zákazníci pod partnerem <strong>'.(int)$stats['outgoing'].'</strong>, podřízení partneři <strong>'.(int)$stats['children'].'</strong>, účetní reference <strong>'.(int)$stats['financial'].'</strong>.</p>';
            if ($stats['archived']) echo '<p><strong>Affiliate má finanční historii.</strong> Partnerský profil bude deaktivován a archivován (kvůli vyúčtování), nikoli fyzicky smazán. Aktivní lifetime vazby se zruší.</p>';
        } else {
            echo '<p><strong>Fresh Affiliate není aktivní:</strong> bez něj nelze garantovat vyčištění affiliate dat. Před smazáním jej aktivuj.</p>';
        }
        echo '<p><strong>Pozor:</strong> existující WooCommerce objednávky, faktury, skutečné provize, výplaty a soubory v médiích zůstanou zachovány. Testuj na neostrých objednávkách. </p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="arc_delete_test_member"><input type="hidden" name="user_id" value="'.$uid.'">';
        wp_nonce_field('arc_delete_test_member_'.$uid);
        echo '<label>Pro potvrzení opiš přesný e-mail účtu <strong>'.esc_html($user->user_email).'</strong><input type="email" class="regular-text" name="confirm_email" autocomplete="off" required></label>';
        echo '<p><label>Důvod odstranění (audit)<input type="text" name="delete_reason" class="regular-text" maxlength="180" value="Opakovaný test registrace" required></label></p>';
        echo '<p><label><input type="checkbox" name="confirm_delete" value="yes" required> Rozumím, že účet a aktivní komunitní i partnerské vazby budou nevratně smazány a finanční doklady zůstanou zachovány.</label></p>';
        echo '<button type="submit" class="button button-secondary" style="border-color:#b32d2e;color:#b32d2e" onclick="return confirm(\'Opravdu trvale odstranit tento testovací účet? Tuto operaci nelze vrátit.\')">Trvale odstranit testovací účet</button>';
        echo '</form></div>';
        return (string)ob_get_clean();
    }
    public static function delete_test_member(): void {
        self::denied();
        $uid=absint($_POST['user_id']??0);
        check_admin_referer('arc_delete_test_member_'.$uid);
        if (!$uid || !self::can_remove_member($uid)) wp_die('Účet nelze odstranit. Je chráněný nebo má redakční obsah.',403);
        $user=get_userdata($uid);
        $confirm=sanitize_email((string)wp_unslash($_POST['confirm_email']??''));
        if (!$user || !hash_equals(strtolower((string)$user->user_email),strtolower($confirm))
            || (string)($_POST['confirm_delete']??'')!=='yes') wp_die('Nesouhlasí potvrzení odstranění.',400);
        if (!class_exists('Fresh_Affiliate_User_Cleanup')) wp_die('Pro úplné vyčištění nejprve aktivuj Fresh Affiliate 0.6.0-beta.3.',409);
        $reason=mb_substr(sanitize_text_field((string)wp_unslash($_POST['delete_reason']??'')),0,180);
        if (mb_strlen($reason)<5) wp_die('Zadej důvod odstranění.',400);
        $actor=get_current_user_id();
        $affiliate_stats=Fresh_Affiliate_User_Cleanup::preview($uid);
        require_once ABSPATH.'wp-admin/includes/user.php';
        // Reassign any residual authored material to the executing administrator:
        // community posts are permanently removed by ARC_User_Cleanup::purge().
        $ok=wp_delete_user($uid,get_current_user_id());
        if (!$ok || get_userdata($uid)) wp_die('WordPress účet se nepodařilo smazat.',500);
        $audit=get_option('arc_member_delete_audit',[]);
        if(!is_array($audit))$audit=[];
        $audit[]=['deleted_user_id'=>$uid,'email_sha256'=>hash('sha256',strtolower($confirm)),
                  'deleted_by'=>$actor,'reason'=>$reason,'created_at_gmt'=>current_time('mysql',true),
                  'affiliate_financial_rows'=>(int)$affiliate_stats['financial']];
        update_option('arc_member_delete_audit',array_slice($audit,-100),false);
        wp_safe_redirect(add_query_arg(['page'=>'arc-members','member_deleted'=>1],admin_url('admin.php')));
        exit;
    }
    public static function save_member(): void {
        self::denied();$id=absint($_POST['user_id']??0);
        if(!$id||!get_userdata($id)||!current_user_can('edit_user',$id))wp_die('Účet nenalezen.',404);
        check_admin_referer('arc_manage_member_'.$id);
        $alias=mb_substr(sanitize_text_field((string)wp_unslash($_POST['alias']??'')),0,65);
        $bio=ARC_Profile::bio_html((string)wp_unslash($_POST['bio']??''));
        if(mb_strlen(wp_strip_all_tags($bio))>1400)wp_die('Popis O mně je příliš dlouhý.',400);
        $interests=mb_substr(sanitize_text_field((string)wp_unslash($_POST['interests']??'')),0,250);
        $v=sanitize_key((string)wp_unslash($_POST['visibility']??'private'));
        if(!in_array($v,['private','network','public'],true))$v='private';
        $avatar=absint($_POST['avatar_id']??0);
        if($avatar && (!wp_attachment_is_image($avatar) || get_post_status($avatar)==='trash'))wp_die('Fotografie není platný obrázek.',400);
        update_user_meta($id,'_arc_profile_alias',$alias);
        if($alias!==''){
            $updated=wp_update_user(['ID'=>$id,'display_name'=>$alias,'nickname'=>$alias]);
            if(is_wp_error($updated))wp_die('Nepodařilo se aktualizovat zobrazované jméno.',500);
        }
        update_user_meta($id,ARC_Data::META_BIO,$bio);
        update_user_meta($id,ARC_Data::META_INTERESTS,$interests);
        update_user_meta($id,'_arc_profile_visibility',$v);
        update_user_meta($id,ARC_Data::META_PUBLIC,$v==='public'?'1':'0');
        if($avatar)update_user_meta($id,'_arc_avatar_attachment_id',$avatar);
        else delete_user_meta($id,'_arc_avatar_attachment_id');
        update_user_meta($id,'_arc_community_suspended',empty($_POST['suspended'])?'0':'1');
        wp_safe_redirect(add_query_arg(['page'=>'arc-members','user_id'=>$id,'saved'=>1],admin_url('admin.php')));exit;
    }
    public static function messages(): void {
        self::denied();echo '<div class="wrap"><h1>AR Community – centrum oznámení</h1>';
        echo '<p>Interní oznámení členům sítě. Promo se doručí pouze členům, kteří výslovně povolili obchodní zprávy. Prohlížečové push zprávy nejsou aktivované. Provozní e-mailová oznámení jsou zapnutá, pokud je člen nevypne ve svém profilu; obchodní e-maily vyžadují souhlas.</p>';
        if(isset($_GET['created']))echo '<div class="notice notice-success"><p>Oznámení zařazeno do fronty. WordPress Cron zpracuje postupně jednotlivé účty.</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="arc_send_broadcast">';wp_nonce_field('arc_send_broadcast');
        echo '<table class="form-table"><tr><th>Typ</th><td><select name="message_type"><option value="service">Provozní informace sítě</option><option value="promo">Dobrovolné obchodní oznámení</option></select></td></tr>';
        echo '<tr><th>Komu</th><td><select name="audience"><option value="all">Všichni způsobilí členové</option><option value="affiliate">Affiliate partneři</option><option value="business">Obchodní partneři</option></select></td></tr>';
        echo '<tr><th>Zpráva</th><td><input name="title" class="large-text" maxlength="220" required placeholder="Nová výzva v komunitě"></td></tr>';
        echo '<tr><th>Odkaz</th><td><input name="target_url" class="large-text" type="url" value="'.esc_attr(home_url('/app/')).'"><p class="description">Odkaz musí vést na Adrenaline Race.</p></td></tr></table>';
        submit_button('Vytvořit a zařadit oznámení');echo '</form>';
        $posts=get_posts(['post_type'=>'arc_broadcast','post_status'=>'private','posts_per_page'=>15,'orderby'=>'date','order'=>'DESC']);
        echo '<h2>Poslední kampaně</h2><table class="widefat striped"><thead><tr><th>Oznámení</th><th>Typ</th><th>Cílení</th><th>Stav</th><th>Doručeno</th></tr></thead><tbody>';
        foreach($posts as $p)echo '<tr><td>'.esc_html($p->post_title).'</td><td>'.esc_html(get_post_meta($p->ID,'_arc_kind',true)).'</td><td>'.esc_html(get_post_meta($p->ID,'_arc_audience',true)).'</td><td>'.esc_html(get_post_meta($p->ID,'_arc_state',true)).'</td><td>'.absint(get_post_meta($p->ID,'_arc_sent',true)).'</td></tr>';
        echo '</tbody></table></div>';
    }
    public static function send_broadcast(): void {
        self::denied();check_admin_referer('arc_send_broadcast');
        if(get_transient('arc_broadcast_lock'))wp_die('Oznámení byla právě odeslána. Opakuj za chvíli.',429);
        $kind=sanitize_key((string)wp_unslash($_POST['message_type']??''));
        $target=sanitize_key((string)wp_unslash($_POST['audience']??''));
        $message=mb_substr(sanitize_text_field((string)wp_unslash($_POST['title']??'')),0,220);
        $url=esc_url_raw((string)wp_unslash($_POST['target_url']??''));
        if(!in_array($kind,['service','promo'],true)||!in_array($target,['all','affiliate','business'],true)||mb_strlen($message)<4)wp_die('Neplatné oznámení.',400);
        if(strtolower((string)wp_parse_url($url,PHP_URL_HOST))!==strtolower((string)wp_parse_url(home_url(),PHP_URL_HOST)))wp_die('Odkaz musí být interní.',400);
        $id=wp_insert_post(['post_type'=>'arc_broadcast','post_status'=>'private','post_author'=>get_current_user_id(),'post_title'=>$message],true);
        if(is_wp_error($id))wp_die('Nelze vytvořit oznámení.',500);
        foreach(['_arc_kind'=>$kind,'_arc_audience'=>$target,'_arc_target_url'=>$url,'_arc_state'=>'queued','_arc_last_id'=>0,'_arc_sent'=>0] as $key=>$v)update_post_meta($id,$key,$v);
        set_transient('arc_broadcast_lock',1,20);
        wp_schedule_single_event(time()+3,'arc_deliver_broadcast',[$id]);
        wp_safe_redirect(add_query_arg(['page'=>'arc-messages','created'=>$id],admin_url('admin.php')));exit;
    }
    public static function deliver_broadcast(int $id): void {
        $p=get_post($id);if(!$p || $p->post_type!=='arc_broadcast'||get_post_meta($id,'_arc_state',true)==='done')return;
        global $wpdb;$cursor=absint(get_post_meta($id,'_arc_last_id',true));
        $ids=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE ID>%d ORDER BY ID ASC LIMIT 75",$cursor));
        $kind=(string)get_post_meta($id,'_arc_kind',true);$audience=(string)get_post_meta($id,'_arc_audience',true);
        $table=$wpdb->prefix.'arc_notifications';$sent=0;
        foreach($ids as $raw){
            $uid=(int)$raw;$cursor=$uid;
            if($kind==='promo' && !get_user_meta($uid,'_arc_marketing_optin',true))continue;
            if($audience==='affiliate' && ARC_Profile::effective_type($uid)!=='affiliate')continue;
            if($audience==='business' && ARC_Profile::effective_type($uid)!=='business')continue;
            $res=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (user_id,actor_id,event_key,kind,object_id,is_read,created_at) VALUES (%d,%d,%s,'broadcast',%d,0,%s)",
              $uid,(int)$p->post_author,'broadcast:'.$id.':'.$uid,$id,current_time('mysql',true)));
            if($res){$sent++;ARC_Notifications::schedule_mail();}
        }
        update_post_meta($id,'_arc_last_id',$cursor);
        update_post_meta($id,'_arc_sent',absint(get_post_meta($id,'_arc_sent',true))+$sent);
        if(count($ids)===75){
            update_post_meta($id,'_arc_state','sending');
            wp_schedule_single_event(time()+12,'arc_deliver_broadcast',[$id]);
        }else update_post_meta($id,'_arc_state','done');
    }
}
