<?php
/** Remove community-only data when a WP user is permanently deleted. */
defined('ABSPATH') || exit;
final class ARC_User_Cleanup {
    public static function boot(): void {
        // Before WordPress reassigns/deletes posts – do not depend on its reassign setting.
        add_action('delete_user',[self::class,'purge'],5,1);
        // Full removal in multisite, not just removal from a blog.
        if(is_multisite())add_action('wpmu_delete_user',[self::class,'purge'],5,1);
    }
    public static function purge(int $uid): void {
        if($uid<=0)return;
        global $wpdb;
        $postsToRemove=[];
        // Only AR-owned content. Ordinary magazines/Woo orders are handled by their owners.
        foreach([ARC_Wall::TYPE,ARC_Data::TYPE,'arc_broadcast'] as $type){
            do{
                // Include trash, auto-drafts and private records; WP_Query any skips trash.
                $ids=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_author=%d AND post_type=%s ORDER BY ID ASC LIMIT 100",$uid,$type));
                if(!$ids)break;
                $removed=0;
                foreach($ids as $id){
                    $deleted=wp_delete_post((int)$id,true);
                    if($deleted){$postsToRemove[]=(int)$id;$removed++;}
                }
                // Prevent a loop if a third-party plugin blocks deletion.
                if(!$removed)break;
            }while(count($ids)===100);
        }
        // Shares whose source community post was deleted are no longer meaningful.
        // Delete only generated AR shares, never independent articles by others.
        foreach(array_chunk($postsToRemove,150) as $chunk){
            $ids=implode(',',array_map('absint',$chunk));
            if($ids==='')continue;
            $shares=$wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_arc_shared_id' AND CAST(meta_value AS UNSIGNED) IN ($ids) LIMIT 2000");
            foreach(array_unique(array_map('absint',$shares)) as $sid){
                $p=get_post($sid);
                if($p instanceof WP_Post && $p->post_type===ARC_Wall::TYPE){
                    if(wp_delete_post($sid,true))$postsToRemove[]=$sid;
                }
            }
        }
        $rels=$wpdb->prefix.'arc_relations';$comments=$wpdb->prefix.'arc_wall_comments';$reactions=$wpdb->prefix.'arc_wall_reactions';$notifs=$wpdb->prefix.'arc_notifications';$commentLikes=$wpdb->prefix.'arc_wall_comment_likes';
        $wpdb->delete($rels,['user_id'=>$uid],['%d']);
        $wpdb->delete($rels,['target_id'=>$uid],['%d']);
        $wpdb->delete($commentLikes,['user_id'=>$uid],['%d']);
        $wpdb->query($wpdb->prepare("DELETE cl FROM $commentLikes cl INNER JOIN $comments c ON c.id=cl.comment_id WHERE c.user_id=%d",$uid));
        $wpdb->delete($comments,['user_id'=>$uid],['%d']);
        $wpdb->delete($reactions,['user_id'=>$uid],['%d']);
        $wpdb->delete($notifs,['user_id'=>$uid],['%d']);
        $wpdb->delete($notifs,['actor_id'=>$uid],['%d']);
        // Remove orphan social interactions on deleted community posts.
        foreach(array_chunk($postsToRemove,150) as $chunk){
            $ids=implode(',',array_map('absint',$chunk));
            if($ids==='')continue;
            $wpdb->query("DELETE cl FROM $commentLikes cl INNER JOIN $comments c ON c.id=cl.comment_id WHERE c.post_id IN ($ids)");
            $wpdb->query("DELETE FROM $comments WHERE post_id IN ($ids)");
            $wpdb->query("DELETE FROM $reactions WHERE post_id IN ($ids)");
            $wpdb->query("DELETE FROM $notifs WHERE object_id IN ($ids) AND kind IN ('post','friend_share')");
        }
        // Member feedback belongs to the deleted account and is not accounting data.
        $feedback=$wpdb->prefix.'arc_feedback';
        $wpdb->delete($feedback,['user_id'=>$uid],['%d']);
        // User meta (including saved items, profile preferences and avatar reference)
        // is deleted by WordPress itself. Do not delete shared images or Woo/affiliate records.
    }
}
