import { useEffect, useState } from 'react';

function UserAvatar({ user, className = 'size-10', textClassName = 'text-sm' }) {
  const [imageFailed, setImageFailed] = useState(false);

  useEffect(() => setImageFailed(false), [user?.avatar_url]);

  return (
    <div className={`user-avatar ${className} grid aspect-square shrink-0 place-items-center overflow-hidden rounded-full bg-[#800000] ring-2 ring-white/20`}>
      {user?.avatar_url && !imageFailed ? (
        <img
          src={user.avatar_url}
          alt={`${user.name || 'User'} profile`}
          className="block h-full w-full aspect-square rounded-full object-cover object-center"
          onError={() => setImageFailed(true)}
        />
      ) : (
        <span className={`${textClassName} font-black uppercase text-white`}>{user?.name?.charAt(0) || 'U'}</span>
      )}
    </div>
  );
}

export default UserAvatar;
